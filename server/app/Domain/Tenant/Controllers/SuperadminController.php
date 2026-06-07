<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Domain\Tenant\Services\AuditLogger;
use App\Mail\TenantWelcomeMail;

class SuperadminController extends Controller
{
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
            ->select(
                'businesses.id',
                'businesses.name as business_name',
                'users.first_name',
                'users.last_name',
                'users.email as owner_email',
                'businesses.is_active',
                'subscriptions.current_period_end as subscription_expires_at',
                'businesses.created_at',
                'businesses.active_modules',
                'subscriptions.plan_id',
                'plans.name as plan_name',
                'plans.enabled_modules as plan_modules'
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

        $businesses->getCollection()->transform(function ($b) {
            $b->owner_name = trim(($b->first_name ?? '') . ' ' . ($b->last_name ?? ''));
            
            $planModules = is_string($b->plan_modules) ? json_decode($b->plan_modules, true) : ($b->plan_modules ?? ['pos', 'inventory']);
            $b->active_modules = $b->active_modules ? (is_string($b->active_modules) ? json_decode($b->active_modules, true) : $b->active_modules) : $planModules;
            
            unset($b->first_name, $b->last_name);
            return $b;
        });

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

        DB::table('businesses')
            ->where('id', $id)
            ->update(['active_modules' => json_encode($request->active_modules)]);

        return response()->json(['message' => 'Modules updated successfully']);
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
            'subdomain' => 'nullable|string|unique:businesses,subdomain|regex:/^[a-zA-Z0-9\-]+$/',
            'custom_domain' => 'nullable|string|unique:businesses,custom_domain'
        ]);

        // Find or create user
        $user = \App\Domain\IAM\Models\User::firstOrCreate(
            ['email' => $request->owner_email],
            ['first_name' => 'Tenant', 'last_name' => 'Owner', 'password' => bcrypt($request->password)]
        );

        $business = \App\Domain\Tenant\Models\Business::create([
            'name' => $request->name,
            'owner_id' => $user->id,
            'subdomain' => $request->subdomain ?? null,
            'custom_domain' => $request->custom_domain ?? null,
            'time_zone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        
        $user->update(['business_id' => $business->id]);
        $user->assignRole('BusinessAdmin');

        // Provision subscription (and license if hybrid/mobile plan)
        $plan = \App\Domain\Tenant\Models\Plan::findOrFail($request->plan_id);
        $provisionAction = new \App\Domain\Tenant\Actions\ProvisionSubscriptionAction();
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
        
        $business = DB::table('businesses')->where('id', $id)->first();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $businessName = $business->name;
        $businessId   = (int) $id;

        DB::transaction(function () use ($id) {
            DB::table('subscriptions')->where('business_id', $id)->delete();
            DB::table('device_activations')->where('business_id', $id)->delete();
            DB::table('licenses')->where('tenant_id', $id)->delete();
            DB::table('businesses')->where('id', $id)->delete();
        });

        AuditLogger::tenantDeleted($request->user(), $businessName, $businessId);

        return response()->json(['message' => 'Tenant and all associated data permanently deleted']);
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

        $totalTenants = DB::table('businesses')->count();
        $activeSubscriptions = DB::table('subscriptions')->where('status', 'active')->count();
        $totalPlans = DB::table('plans')->count();
        
        $revenue = 0;
        if (DB::getSchemaBuilder()->hasTable('payments')) {
            $revenue = DB::table('payments')->sum('amount');
        }

        // Calculate MRR and ARR
        $activeSubs = DB::table('subscriptions')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->select('plans.price', 'plans.interval')
            ->get();
            
        $mrr = 0;
        foreach ($activeSubs as $sub) {
            if ($sub->interval === 'year') {
                $mrr += ($sub->price / 12);
            } else {
                $mrr += $sub->price;
            }
        }
        $arr = $mrr * 12;

        return response()->json([
            'total_tenants' => $totalTenants,
            'active_subscriptions' => $activeSubscriptions,
            'total_plans' => $totalPlans,
            'lifetime_revenue' => $revenue,
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
        ]);
    }

    public function impersonate(Request $request, $business_id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        
        $targetBusiness = DB::table('businesses')->where('id', $business_id)->first();
        if (!$targetBusiness) return response()->json(['message' => 'Business not found'], 404);
        
        // Find the primary owner or a BusinessAdmin
        $owner = \App\Domain\IAM\Models\User::where('business_id', $business_id)
            ->whereHas('roles', function($q) { $q->where('name', 'BusinessAdmin'); })
            ->first();
            
        if (!$owner) {
            $owner = \App\Domain\IAM\Models\User::where('id', $targetBusiness->owner_id)->first();
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
        $licenses = \App\Domain\Tenant\Models\License::with(['tenant', 'plan'])->orderBy('created_at', 'desc')->get();
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
        $planType = $plan->plan_type ?? 'web';
        $tokenType = match ($planType) {
            'hybrid_offline_sync' => 'hybrid',
            'mobile_native'       => 'mobile',
            default               => 'web',
        };

        $licenseService = new \App\Domain\Tenant\Services\LicenseKeyService();
        $licenseKey = $licenseService->generateKey($request->tenant_id, $request->plan_id, $tokenType);

        // Deactivate existing licenses for this tenant
        \App\Domain\Tenant\Models\License::where('tenant_id', $request->tenant_id)->update(['status' => 'suspended']);

        $license = \App\Domain\Tenant\Models\License::create([
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
        $license = \App\Domain\Tenant\Models\License::findOrFail($id);
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
            $activation = \App\Domain\Tenant\Models\DeviceActivation::where('license_key', $request->license_key)
                ->where('status', 'active')
                ->first();

            if (!$activation) {
                // Check if it's a new activation for a business
                $activation = \App\Domain\Tenant\Models\DeviceActivation::where('license_key', $request->license_key)->first();
                if (!$activation) return response()->json(['message' => 'Invalid license key'], 404);
            }

            // Check limits
            $business = \App\Domain\Tenant\Models\Business::find($activation->business_id);
            $subscription = DB::table('subscriptions')->where('business_id', $business->id)->where('status', 'active')->first();
            if (!$subscription) return response()->json(['message' => 'No active subscription'], 403);
            
            $plan = DB::table('plans')->where('id', $subscription->plan_id)->first();
            
            // Apply pessimistic lock here to prevent race conditions during activation
            $activeCount = \App\Domain\Tenant\Models\DeviceActivation::where('business_id', $business->id)
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
