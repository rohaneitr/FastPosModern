<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperadminController extends Controller
{
    private function generateCleanKey(): string
    {
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $key = '';
        for ($i = 0; $i < 4; $i++) {
            $block = '';
            for ($j = 0; $j < 4; $j++) {
                $block .= $pool[random_int(0, strlen($pool) - 1)];
            }
            $key .= $block . ($i < 3 ? '-' : '');
        }
        return $key;
    }

    /**
     * Get all businesses/tenants for the SaaS dashboard.
     * Note: In a real app, this must be restricted to Superadmins only!
     */
    public function businesses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        $query = DB::table('businesses')
            ->join('users', 'businesses.owner_id', '=', 'users.id')
            ->leftJoin('subscriptions', function($join) {
                $join->on('subscriptions.business_id', '=', 'businesses.id')
                     ->where('subscriptions.status', '=', 'active');
            })
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->whereNull('businesses.deleted_at')
            ->select(
                'businesses.id',
                'businesses.name as business_name',
                DB::raw("users.first_name || ' ' || users.last_name as owner_name"),
                'users.email as owner_email',
                'businesses.is_active',
                'businesses.subscription_expires_at',
                'businesses.created_at'
            );

        if ($request->has('search') && $request->search != '') {
            $search = strtolower($request->search);
            $query->where(function($q) use ($search) {
                $q->where(DB::raw('LOWER(businesses.name)'), 'like', "%{$search}%")
                  ->orWhere(DB::raw("LOWER(users.first_name || ' ' || users.last_name)"), 'like', "%{$search}%")
                  ->orWhere(DB::raw('LOWER(users.email)'), 'like', "%{$search}%");
            });
        }

        $businesses = $query->orderBy('businesses.created_at', 'desc')->paginate(20);

        return response()->json($businesses);
    }

    /**
     * Toggle business active status (Suspend/Unsuspend)
     */
    public function toggleStatus(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }
        $business = DB::table('businesses')->where('id', $id)->first();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        DB::table('businesses')
            ->where('id', $id)
            ->update(['is_active' => !$business->is_active]);

        return response()->json(['message' => 'Business status updated', 'is_active' => !$business->is_active]);
    }

    /**
     * Update business active modules (feature toggling)
     */
    public function updateModules(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        $request->validate([
            'active_modules' => 'required|array'
        ]);

        $business = DB::table('businesses')->where('id', $id)->first();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $requestedSlugs = $request->active_modules;
        $registry = config('fpm_modules');
        
        DB::transaction(function () use ($business, $requestedSlugs, $registry) {
            // 1. Sync the module definitions if they don't exist in DB
            $moduleIds = [];
            foreach ($requestedSlugs as $slug) {
                if (isset($registry[$slug])) {
                    $module = DB::table('modules')->where('slug', $slug)->first();
                    if (!$module) {
                        $moduleId = DB::table('modules')->insertGetId([
                            'name' => $registry[$slug]['name'],
                            'slug' => $slug,
                            'description' => $registry[$slug]['description'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $moduleIds[] = $moduleId;
                    } else {
                        $moduleIds[] = $module->id;
                    }
                }
            }

            // 2. Sync Pivot Table
            DB::table('tenant_modules')->where('business_id', $business->id)->delete();
            $pivotInserts = array_map(function($modId) use ($business) {
                return [
                    'business_id' => $business->id,
                    'module_id' => $modId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $moduleIds);
            
            if (!empty($pivotInserts)) {
                DB::table('tenant_modules')->insert($pivotInserts);
            }

            // 3. Update the denormalized JSON for fast auth reads
            DB::table('businesses')
                ->where('id', $business->id)
                ->update(['active_modules' => json_encode($requestedSlugs)]);

            // 4. Update Spatie Permissions for the BusinessOwner
            $owner = \App\Modules\IAM\Models\User::where('id', $business->owner_id)->first();
            if ($owner) {
                $permissionsToSync = [];
                foreach ($requestedSlugs as $slug) {
                    $permName = "module.{$slug}";
                    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'sanctum']);
                    $permissionsToSync[] = $permName;
                }
                
                // Fetch all current module.* permissions the owner has directly
                $currentModulePerms = $owner->permissions()->where('name', 'like', 'module.%')->pluck('name')->toArray();
                
                // Calculate diffs
                $permsToRemove = array_diff($currentModulePerms, $permissionsToSync);
                $permsToAdd = array_diff($permissionsToSync, $currentModulePerms);
                
                if (!empty($permsToRemove)) {
                    $owner->revokePermissionTo($permsToRemove);
                }
                if (!empty($permsToAdd)) {
                    $owner->givePermissionTo($permsToAdd);
                }
            }
        });

        \Illuminate\Support\Facades\Cache::forget("tenant_modules:{$id}");

        return response()->json(['message' => 'Modules synced and authorized successfully']);
    }

    /**
     * Renew business subscription (+1 month, +1 year)
     */
    public function renewSubscription(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $request->validate([
            'duration' => 'required|in:1_month,1_year'
        ]);

        $business = DB::table('businesses')->where('id', $id)->first();
        if (!$business) return response()->json(['message' => 'Business not found'], 404);

        $currentEnd = $business->subscription_ends_at ? \Carbon\Carbon::parse($business->subscription_ends_at) : now();
        // If expired, start from today
        if ($currentEnd->isPast()) {
            $currentEnd = now();
        }

        if ($request->duration === '1_month') {
            $newEnd = $currentEnd->addMonth();
        } else {
            $newEnd = $currentEnd->addYear();
        }

        DB::table('businesses')
            ->where('id', $id)
            ->update([
                'subscription_ends_at' => $newEnd->format('Y-m-d'),
                'subscription_status' => 'Active',
                'is_active' => true
            ]);

        return response()->json([
            'message' => 'Subscription renewed successfully',
            'subscription_ends_at' => $newEnd->format('Y-m-d'),
            'subscription_status' => 'Active'
        ]);
    }

    /**
     * Override business subscription status
     */
    public function overrideSubscription(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $request->validate([
            'subscription_status' => 'required|in:Active,Expired,Suspended',
            'subscription_ends_at' => 'nullable|date'
        ]);

        $business = DB::table('businesses')->where('id', $id)->first();
        if (!$business) return response()->json(['message' => 'Business not found'], 404);

        $updateData = [
            'subscription_status' => $request->subscription_status,
        ];
        
        if ($request->has('subscription_ends_at')) {
            $updateData['subscription_ends_at'] = $request->subscription_ends_at;
        }

        if ($request->subscription_status !== 'Active') {
            $updateData['is_active'] = false;
        } else {
            $updateData['is_active'] = true;
        }

        DB::table('businesses')->where('id', $id)->update($updateData);

        return response()->json(['message' => 'Subscription status overridden successfully']);
    }

    public function storeBusiness(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        $request->validate([
            'name' => 'required|string',
            'owner_email' => 'required|email',
            'password' => 'required|string|min:8',
            'plan_id' => 'required|exists:plans,id',
            'subdomain' => [
                'nullable', 'string', 'regex:/^[a-zA-Z0-9\-]+$/',
                \Illuminate\Validation\Rule::unique('businesses', 'subdomain')->whereNull('deleted_at')
            ],
            'custom_domain' => [
                'nullable', 'string',
                \Illuminate\Validation\Rule::unique('businesses', 'custom_domain')->whereNull('deleted_at')
            ]
        ]);

        // Find or create user
        $user = \App\Modules\IAM\Models\User::firstOrCreate(
            ['email' => $request->owner_email],
            ['first_name' => 'Tenant', 'last_name' => 'Owner', 'password' => bcrypt($request->password)]
        );

        $business = \App\Modules\Tenant\Models\Business::create([
            'name' => $request->name,
            'owner_id' => $user->id,
            'subdomain' => $request->subdomain ?? null,
            'custom_domain' => $request->custom_domain ?? null,
            'time_zone' => 'Asia/Dhaka',
            'is_active' => true,
            'status' => 'pending_activation',
            'license_key' => null,
            'active_modules' => [],
            'trial_ends_at' => now()->addDays(30),
        ]);
        
        $user->update(['business_id' => $business->id]);
        $user->assignRole('BusinessAdmin');

        // Provision subscription (and license if hybrid/mobile plan)
        $plan = \App\Modules\Tenant\Models\Plan::findOrFail($request->plan_id);
        $provisionAction = new \App\Modules\Tenant\Actions\ProvisionSubscriptionAction();
        $provisionAction->execute($business->id, $plan);

        // Grab the license key if one was just created
        $license = DB::table('licenses')
            ->where('tenant_id', $business->id)
            ->orderByDesc('id')
            ->first();
        $licenseKey = $license?->license_key;

        // Queue welcome email to the new owner
        try {
            $plan = DB::table('plans')->where('id', $request->plan_id)->first();
            Mail::to($request->owner_email)->queue(new TenantWelcomeMail(
                businessName:      $request->name,
                ownerEmail:        $request->owner_email,
                temporaryPassword: $request->password, // plain-text as entered by SuperAdmin
                planName:          $plan->name ?? 'Standard',
                licenseKey:        $licenseKey,
            ));
        } catch (\Throwable $e) {
            Log::error('TenantWelcomeMail queue failed', [
                'business_id' => $business->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message'     => 'Business created successfully',
            'business'    => $business,
            'license_key' => $licenseKey
        ]);
    }

    public function destroyBusiness(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }

        $business = \App\Modules\Tenant\Models\Business::find($id);
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $businessName = $business->name;
        $businessId   = (int) $id;

        try {
            // Use Eloquent soft delete so the booted() event fires and cascades soft deletes
            $business->delete();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[destroyBusiness] Soft delete failed', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete tenant: ' . $e->getMessage()
            ], 500);
        }

        AuditLogger::tenantDeleted($request->user(), $businessName, $businessId);

        return response()->json([
            'message' => 'Tenant and all associated data soft-deleted. It will be permanently removed in 30 days.',
        ]);
    }



    public function restoreBusiness(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }

        $business = \App\Modules\Tenant\Models\Business::withTrashed()->find($id);
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        if (!$business->trashed()) {
            return response()->json(['message' => 'Business is not deleted'], 400);
        }

        try {
            $business->restore(); // This will trigger the restoring event and cascade to relations
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[restoreBusiness] Restore failed', [
                'business_id' => $id,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to restore tenant: ' . $e->getMessage()
            ], 500);
        }

        // We should also log this
        // AuditLogger::tenantRestored($request->user(), $business->name, $business->id); // Assuming we might want to log this

        return response()->json([
            'message' => 'Tenant and associated data successfully restored.'
        ]);
    }

    public function monitoring(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $totalBusinesses = DB::table('businesses')->count();
        $activeBusinesses = DB::table('businesses')->where('is_active', true)->count();
        $totalUsers = DB::table('users')->count();
        $activeDevices = DB::table('device_activations')->where('status', 'active')->count();

        $metrics = [
            ['label' => 'Total Tenants', 'value' => $totalBusinesses, 'status' => 'healthy', 'icon' => 'ðŸ¢'],
            ['label' => 'Active Tenants', 'value' => $activeBusinesses, 'status' => 'healthy', 'icon' => 'ðŸŸ¢'],
            ['label' => 'Total Users', 'value' => $totalUsers, 'status' => 'healthy', 'icon' => 'ðŸ‘¥'],
            ['label' => 'Active Devices', 'value' => $activeDevices, 'status' => 'healthy', 'icon' => 'ðŸ’»'],
        ];

        $events = [
            ['time' => 'Just now', 'event' => 'Live Telemetry Active', 'type' => 'success']
        ];

        return response()->json(['metrics' => $metrics, 'events' => $events]);
    }

    public function overviewStats(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }

        $totalTenants = 0;
        try {
            $totalTenants = DB::table('businesses')->count();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OverviewStats: Total Tenants Failed', ['error' => $e->getMessage()]);
        }

        $activeSubscriptions = 0;
        try {
            $activeSubscriptions = DB::table('subscriptions')->where('status', 'active')->count();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OverviewStats: Active Subscriptions Failed', ['error' => $e->getMessage()]);
        }

        $totalPlans = 0;
        try {
            $totalPlans = DB::table('plans')->count();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OverviewStats: Total Plans Failed', ['error' => $e->getMessage()]);
        }
        
        $revenue = 0;
        try {
            if (DB::getSchemaBuilder()->hasTable('payments')) {
                $revenue = DB::table('payments')->sum('amount');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OverviewStats: Revenue Failed', ['error' => $e->getMessage()]);
        }

        // Calculate MRR and ARR
        $mrr = 0;
        $arr = 0;
        try {
            $activeSubs = DB::table('subscriptions')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->where('subscriptions.status', 'active')
                ->select('plans.price', 'plans.interval')
                ->get();
                
            foreach ($activeSubs as $sub) {
                if ($sub->interval === 'year') {
                    $mrr += ($sub->price / 12);
                } else {
                    $mrr += $sub->price;
                }
            }
            $arr = $mrr * 12;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OverviewStats: MRR/ARR Failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'total_tenants' => $totalTenants,
            'active_subscriptions' => $activeSubscriptions,
            'total_plans' => $totalPlans,
            'revenue' => $revenue, // changed from lifetime_revenue to revenue
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'recent_activity' => [], // Added missing key to prevent frontend map() crash
        ]);
    }

    public function impersonate(Request $request, $business_id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $targetBusiness = DB::table('businesses')->where('id', $business_id)->first();
        if (!$targetBusiness) return response()->json(['message' => 'Business not found'], 404);
        
        // Find the primary owner or a BusinessAdmin
        $owner = \App\Modules\IAM\Models\User::where('business_id', $business_id)
            ->whereHas('roles', function($q) { $q->where('name', 'BusinessAdmin'); })
            ->first();
            
        if (!$owner) {
            $owner = \App\Modules\IAM\Models\User::where('id', $targetBusiness->owner_id)->first();
        }
        
        if (!$owner) {
            return response()->json(['message' => 'No admin user found for this tenant'], 404);
        }
        
        // Generate a temporary token
        $token = $owner->createToken('impersonation_token')->plainTextToken;
        
        return response()->json([
            'message' => 'Impersonation successful',
            'token' => $token,
            'business_name' => $targetBusiness->name,
            'user' => $owner
        ]);
    }

    public function toggleMaintenance(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $currentState = \Illuminate\Support\Facades\Cache::get('global_maintenance_mode', false);
        $newState = !$currentState;
        
        \Illuminate\Support\Facades\Cache::put('global_maintenance_mode', $newState);
        
        return response()->json([
            'message' => 'Maintenance mode updated',
            'maintenance_mode' => $newState
        ]);
    }
    
    public function getMaintenanceStatus(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }

        return response()->json([
            'maintenance_mode' => \Illuminate\Support\Facades\Cache::get('global_maintenance_mode', false)
        ]);
    }

    public function getLicenses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        $licenses = \App\Modules\Tenant\Models\License::with(['tenant', 'plan'])->orderBy('created_at', 'desc')->get();
        return response()->json($licenses);
    }

    public function generateLicense(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        $request->validate([
            'tenant_id' => 'required|exists:businesses,id',
            'plan_id' => 'required|exists:plans,id'
        ]);

        $plan = DB::table('plans')->where('id', $request->plan_id)->first();

        $licenseKey = $this->generateCleanKey();

        // Deactivate existing licenses for this tenant
        \App\Modules\Tenant\Models\License::where('tenant_id', $request->tenant_id)->update(['status' => 'suspended']);

        $license = \App\Modules\Tenant\Models\License::create([
            'tenant_id' => $request->tenant_id,
            'plan_id' => $request->plan_id,
            'license_key' => $licenseKey,
            'status' => 'active',
            'device_limit' => $plan->device_limit ?? 1,
            'employee_limit' => $plan->employee_limit ?? 1,
            'expires_at' => $plan->interval === 'year' ? now()->addYear() : now()->addMonth(),
        ]);

        return response()->json([
            'message' => 'License key generated successfully',
            'license_key' => $licenseKey,
            'license' => $license->load(['tenant', 'plan'])
        ], 201);
    }

    public function toggleLicenseStatus(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        $license = \App\Modules\Tenant\Models\License::findOrFail($id);
        $license->status = $license->status === 'active' ? 'suspended' : 'active';
        $license->save();

        if ($license->status === 'suspended') {
            AuditLogger::licenseRevoked($request->user(), $license);
        }

        return response()->json(['message' => 'License status updated', 'license' => $license->load(['tenant', 'plan'])]);
    }

    public function activateDevice(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'device_fingerprint' => 'required|string'
        ]);

        return DB::transaction(function () use ($request) {
            $activation = \App\Modules\Tenant\Models\DeviceActivation::where('license_key', $request->license_key)
                ->where('status', 'active')
                ->first();

            if (!$activation) {
                // Check if it's a new activation for a business
                $activation = \App\Modules\Tenant\Models\DeviceActivation::where('license_key', $request->license_key)->first();
                if (!$activation) return response()->json(['message' => 'Invalid license key'], 404);
            }

            // Check limits
            $business = \App\Modules\Tenant\Models\Business::find($activation->business_id);
            $subscription = DB::table('subscriptions')->where('business_id', $business->id)->where('status', 'active')->first();
            if (!$subscription) return response()->json(['message' => 'No active subscription'], 403);
            
            $plan = DB::table('plans')->where('id', $subscription->plan_id)->first();
            
            // Apply pessimistic lock here to prevent race conditions during activation
            $activeCount = \App\Modules\Tenant\Models\DeviceActivation::where('business_id', $business->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->count();

            if ($activeCount >= ($plan->device_limit ?? 1) && $activation->device_fingerprint !== $request->device_fingerprint) {
                return response()->json(['message' => 'Device Quota Exceeded'], 403);
            }

            $activation->update([
                'device_fingerprint' => $request->device_fingerprint,
                'activated_at' => now(),
                'status' => 'active'
            ]);

            return response()->json(['message' => 'Device activated successfully']);
        });
    }

}
