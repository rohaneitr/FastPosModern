<?php

namespace App\Modules\Tenant\Actions;

use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidatePlanTransitionAction
{
    public function execute(int $businessId, int $targetPlanId, Subscription $currentSubscription)
    {
        $targetPlan = Plan::findOrFail($targetPlanId);
        
        // Calculate the proposed new limits by applying the inheritance rule
        $limitOverrides = $currentSubscription->limit_overrides ?? [];
        
        // Helper logic: If a limit is explicitly null, 0, or -1, it represents "Unlimited"
        $baseUser = $targetPlan->user_limit ?? $targetPlan->max_users;
        $isUserUnlimited = $baseUser === null || $baseUser <= 0;
        $targetUserLimit = $isUserUnlimited ? -1 : ($baseUser + ($limitOverrides['user_limit'] ?? 0));

        $baseLocation = $targetPlan->location_limit ?? $targetPlan->max_locations;
        $isLocationUnlimited = $baseLocation === null || $baseLocation <= 0;
        $targetLocationLimit = $isLocationUnlimited ? -1 : ($baseLocation + ($limitOverrides['location_limit'] ?? 0));

        $baseDevice = $targetPlan->device_limit;
        $isDeviceUnlimited = $baseDevice === null || $baseDevice <= 0;
        $targetDeviceLimit = $isDeviceUnlimited ? -1 : ($baseDevice + ($limitOverrides['device_limit'] ?? 0));

        // Fetch current active usage
        $activeUsers = DB::table('users')->where('business_id', $businessId)->count();
        $activeLocations = DB::table('locations')->where('business_id', $businessId)->whereNull('deleted_at')->where('is_active', true)->count();
        
        $activeDevices = DB::table('user_devices')
            ->join('users', 'user_devices.user_id', '=', 'users.id')
            ->where('users.business_id', $businessId)
            ->where('user_devices.status', 'active')
            ->count();

        // Validate limits
        if (!$isUserUnlimited && $activeUsers > $targetUserLimit) {
            throw ValidationException::withMessages([
                'plan_id' => "Cannot downgrade. Your active user count ({$activeUsers}) exceeds the target plan limit ({$targetUserLimit})."
            ]);
        }

        if (!$isLocationUnlimited && $activeLocations > $targetLocationLimit) {
            throw ValidationException::withMessages([
                'plan_id' => "Cannot downgrade. Your active location count ({$activeLocations}) exceeds the target plan limit ({$targetLocationLimit})."
            ]);
        }

        if (!$isDeviceUnlimited && $activeDevices > $targetDeviceLimit) {
            throw ValidationException::withMessages([
                'plan_id' => "Cannot downgrade. Your active device count ({$activeDevices}) exceeds the target plan limit ({$targetDeviceLimit})."
            ]);
        }
        
        return true;
    }
}
