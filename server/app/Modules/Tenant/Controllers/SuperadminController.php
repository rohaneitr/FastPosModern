<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Services\AuditLogger;

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
                'businesses.created_at',
                'subscriptions.id as subscription_id',
                'subscriptions.status as subscription_status_real',
                'plans.max_users as plan_max_users',
                'plans.max_locations as plan_max_locations',
                DB::raw('(SELECT count(*) FROM users WHERE users.business_id = businesses.id) as users_count'),
                DB::raw('(SELECT count(*) FROM locations WHERE locations.business_id = businesses.id) as locations_count')
            );

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('businesses.name', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("users.first_name || ' ' || users.last_name ILIKE ?", ["%{$search}%"])
                  ->orWhere('users.email', 'ILIKE', "%{$search}%");
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

        // Wrap everything in an atomic transaction
        $result = DB::transaction(function () use ($request) {
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
                
            return [
                'business' => $business,
                'licenseKey' => $license?->license_key
            ];
        });

        $business = $result['business'];
        $licenseKey = $result['licenseKey'];

        // Queue welcome email to the new owner
        try {
            $plan = DB::table('plans')->where('id', $request->plan_id)->first();
            \Illuminate\Support\Facades\Mail::to($request->owner_email)->queue(new \App\Modules\Tenant\Mail\TenantWelcomeMail(
                $request->name,
                $request->owner_email,
                $request->password, // plain-text temporary password
                $plan->name ?? 'Standard',
                $licenseKey
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Tenant welcome email failed', [
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
            DB::transaction(function () use ($business, $businessId, $request, $businessName) {
                // Use Eloquent soft delete so the booted() event fires and cascades soft deletes
                $business->delete();
                AuditLogger::tenantDeleted($request->user(), $businessName, $businessId);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[destroyBusiness] Soft delete failed', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete tenant: ' . $e->getMessage()
            ], 500);
        }

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
        $activeDevices = DB::table('user_devices')->where('status', 'active')->count();

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

    public function metrics(Request $request)
    {
        return $this->overviewStats($request);
    }

    public function settings(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $smtpPassword = config('mail.mailers.smtp.password');
        
        return response()->json([
            'smtp' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password' => $smtpPassword ? '********' : null,
            ]
        ]);
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
        
        // Generate a temporary token with impersonation context injected into abilities
        $token = $owner->createToken('impersonation_token', ['impersonate', 'admin_id:' . $request->user()->id])->plainTextToken;
        
        // Impersonation Audit Logging
        \App\Modules\SuperAdmin\Models\AuditLog::create([
            'business_id' => $business_id,
            'user_id' => $request->user()->id,
            'event' => 'impersonate_tenant',
            'auditable_type' => 'App\Modules\Tenant\Models\Business',
            'auditable_id' => $business_id,
            'new_values' => ['target_user_id' => $owner->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now()
        ]);
        
        return response()->json([
            'message' => 'Impersonation successful',
            'token' => $token,
            'business_name' => $targetBusiness->name,
            'user' => $owner,
            'original_admin_id' => $request->user()->id
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

    /**
     * Phase 2: License Monitoring API (List Licenses)
     */
    public function getLicenses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        
        $query = Business::with('subscription.plan')
            ->select('businesses.*')
            ->addSelect(DB::raw('(SELECT count(*) FROM user_devices JOIN users ON user_devices.user_id = users.id WHERE users.business_id = businesses.id AND user_devices.status = \'active\') as active_devices_count'))
            ->whereNotNull('license_key');

        $licenses = $query->paginate(15)->through(function($business) {
            $resolvedLimit = $business->subscription ? ($business->subscription->resolved_device_limit ?? 0) : 0;
            return [
                'id' => $business->id,
                'business_name' => $business->name,
                'license_key' => $business->license_key,
                'status' => $business->is_active ? 'active' : 'suspended',
                'active_devices_count' => $business->active_devices_count,
                'resolved_device_limit' => $resolvedLimit === -1 ? 'Unlimited' : $resolvedLimit,
                'subscription_status' => $business->subscription ? $business->subscription->status : 'None',
            ];
        });

        return response()->json($licenses);
    }

    /**
     * Phase 1: The Device Drill-Down API (Backend)
     */
    public function getBusinessDevices(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }

        $devices = DB::table('user_devices')
            ->join('users', 'user_devices.user_id', '=', 'users.id')
            ->where('users.business_id', $id)
            ->select(
                'user_devices.id as device_id',
                'user_devices.device_name',
                'user_devices.os',
                'user_devices.browser as hardware_fingerprint',
                'user_devices.ip_address',
                'user_devices.last_login as last_heartbeat',
                'user_devices.status',
                'users.first_name',
                'users.last_name',
                'users.email'
            )
            ->orderBy('user_devices.last_login', 'desc')
            ->get();

        return response()->json($devices);
    }

    /**
     * Phase 2: Single Device Kill-Switch (Backend)
     */
    public function revokeSingleDevice(Request $request, $device_id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }

        return DB::transaction(function() use ($request, $device_id) {
            $device = DB::table('user_devices')->where('id', $device_id)->first();
            if (!$device) {
                return response()->json(['message' => 'Device not found'], 404);
            }

            DB::table('user_devices')->where('id', $device_id)->update(['status' => 'revoked']);

            // Purge ONLY the specific Sanctum token tied to that hardware_fingerprint
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $device->user_id)
                ->where('tokenable_type', \App\Models\User::class)
                ->where('name', 'POS_Offline_Heartbeat_' . $device->browser)
                ->delete();

            $user = DB::table('users')->where('id', $device->user_id)->first();

            \App\Modules\Tenant\Services\AuditLogger::log(
                $user->business_id,
                $request->user(),
                'single_device_revoked',
                'App\Modules\Tenant\Models\Business',
                $user->business_id,
                [],
                ['device_id' => $device_id, 'device_name' => $device->device_name, 'fingerprint' => $device->browser, 'message' => "Single device manually revoked by SuperAdmin"]
            );

            return response()->json(['message' => 'Device revoked successfully. Token purged.']);
        });
    }

    /**
     * Phase 1: The License Generator & Kill-Switch API
     */
    public function generateLicense(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        return DB::transaction(function() use ($request, $id) {
            $business = Business::findOrFail($id);

            // Generate FPM-XXXX-XXXX-XXXX-XXXX
            $key = 'FPM-' . strtoupper(substr(md5(uniqid()), 0, 4)) . '-' . 
                   strtoupper(substr(md5(uniqid()), 0, 4)) . '-' . 
                   strtoupper(substr(md5(uniqid()), 0, 4)) . '-' . 
                   strtoupper(substr(md5(uniqid()), 0, 4));

            $oldKey = $business->license_key;
            $business->update(['license_key' => $key]);

            // The Kill-Switch
            // 1. Revoke all active offline devices
            DB::table('user_devices')
                ->join('users', 'user_devices.user_id', '=', 'users.id')
                ->where('users.business_id', $business->id)
                ->where('user_devices.status', 'active')
                ->update(['user_devices.status' => 'revoked']);

            // 2. Delete all Heartbeat Tokens for all users of this business to force immediate logout
            $userIds = DB::table('users')->where('business_id', $business->id)->pluck('id');
            DB::table('personal_access_tokens')
                ->whereIn('tokenable_id', $userIds)
                ->where('tokenable_type', \App\Models\User::class)
                ->where('name', 'like', 'POS_Offline_Heartbeat_%')
                ->delete();

            \App\Modules\Tenant\Services\AuditLogger::log(
                $business->id,
                $request->user(),
                'license_regenerated',
                'App\Modules\Tenant\Models\Business',
                $business->id,
                ['license_key' => $oldKey],
                ['license_key' => $key, 'message' => "Master License regenerated. System-wide Kill-Switch engaged."]
            );

            return response()->json([
                'message' => 'License generated successfully. Previous devices revoked.',
                'license_key' => $key
            ]);
        });
    }

    public function toggleLicenseStatus(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        
        $business = Business::findOrFail($id);
        $business->is_active = !$business->is_active;
        $business->save();

        // If suspended, Heartbeat API will fail naturally. 
        // We purge the current offline heartbeat tokens to force an immediate internet check next time they try to use it.
        // But we DO NOT set user_devices.status = 'revoked', so they can automatically reconnect if reactivated.
        if (!$business->is_active) {
            $userIds = DB::table('users')->where('business_id', $business->id)->pluck('id');
            DB::table('personal_access_tokens')
                ->whereIn('tokenable_id', $userIds)
                ->where('tokenable_type', \App\Models\User::class)
                ->where('name', 'like', 'POS_Offline_Heartbeat_%')
                ->delete();
        }

        \App\Modules\Tenant\Services\AuditLogger::log(
            $business->id,
            $request->user(),
            'license_status_toggled',
            'App\Modules\Tenant\Models\Business',
            $business->id,
            ['is_active' => !$business->is_active],
            ['is_active' => $business->is_active, 'message' => "Master License manually " . ($business->is_active ? 'Activated' : 'Suspended') . " by SuperAdmin"]
        );

        return response()->json(['message' => 'Master License status updated', 'business' => $business]);
    }

}
