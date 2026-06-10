<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;

class EntitlementRegistry
{
    /**
     * Builds the definitive, immutable Feature Matrix for a given User/Tenant.
     * This is the Single Source of Truth for the frontend and API Gate.
     */
    public function getMatrix(User $user): array
    {
        $businessId = $user->business_id;

        // System Admins (e.g., FastPOS staff) have absolute access
        if (!$businessId && $user->hasRole('super_admin')) {
            return [
                'is_super_admin' => true,
                'active_modules' => ['all'],
                'permissions' => ['*'],
                'limits' => ['users' => 9999, 'devices' => 9999, 'locations' => 999],
                'subscription_status' => 'Active',
            ];
        }

        $business = DB::table('businesses')->where('id', $businessId)->first();
        if (!$business) {
            return [];
        }

        $plan = DB::table('subscription_plans')->where('id', $business->plan_id)->first();
        $limits = $plan ? json_decode($plan->constraints, true) : [];
        $activeModules = json_decode($business->active_modules ?? '[]', true);

        return [
            'is_super_admin' => false,
            'tenant_id' => $business->id,
            'tenant_name' => $business->name,
            'subscription_status' => $business->subscription_status,
            'billing_due_date' => $business->billing_due_date,
            'active_modules' => $activeModules,
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'limits' => [
                'users' => $limits['max_users'] ?? 1,
                'devices' => $limits['max_devices'] ?? 1,
                'locations' => $limits['max_locations'] ?? 1,
            ]
        ];
    }
}
