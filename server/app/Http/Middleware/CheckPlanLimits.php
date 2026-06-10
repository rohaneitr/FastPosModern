<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckPlanLimits
{
    /**
     * Hard Gatekeeping: Verifies current usage against subscription plan limits.
     */
    public function handle(Request $request, Closure $next, $limitType = null)
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant || !$limitType) {
            return $next($request);
        }

        // 1. Fetch Subscription Plan Constraints (Cached for performance)
        $cacheKey = "tenant_{$tenant->id}_plan_limits";
        $limits = Cache::remember($cacheKey, 3600, function () use ($tenant) {
            $plan = DB::table('subscription_plans')->where('id', $tenant->plan_id)->first();
            return $plan ? json_decode($plan->constraints, true) : null;
        });

        if (!$limits) {
            return response()->json(['message' => 'Subscription plan constraints not found.'], 403);
        }

        // 2. Strict Enforcement Logic
        switch ($limitType) {
            case 'users':
                $userLimit = $limits['max_users'] ?? 1;
                $activeUsers = DB::table('users')->where('business_id', $tenant->id)->count();
                if ($activeUsers >= $userLimit) {
                    return response()->json([
                        'error_code' => 'PLAN_LIMIT_REACHED',
                        'message' => "User limit reached. Your plan allows a maximum of {$userLimit} users."
                    ], 403);
                }
                break;

            case 'locations':
                $locationLimit = $limits['max_locations'] ?? 1;
                $activeLocations = DB::table('locations')->where('business_id', $tenant->id)->count();
                if ($activeLocations >= $locationLimit) {
                    return response()->json([
                        'error_code' => 'PLAN_LIMIT_REACHED',
                        'message' => "Location limit reached. Your plan allows a maximum of {$locationLimit} locations."
                    ], 403);
                }
                break;

            case 'devices':
                // Verify incoming Device Hash against registered devices
                $deviceHash = $request->header('X-Device-Hash');
                if (!$deviceHash) {
                    return response()->json(['message' => 'Device hash is required for this operation.'], 403);
                }
                
                $deviceLimit = $limits['max_devices'] ?? 1;
                $isRegistered = DB::table('user_devices')
                    ->where('business_id', $tenant->id)
                    ->where('device_hash', $deviceHash)
                    ->exists();

                if (!$isRegistered) {
                    $registeredCount = DB::table('user_devices')->where('business_id', $tenant->id)->count();
                    if ($registeredCount >= $deviceLimit) {
                        return response()->json([
                            'error_code' => 'PLAN_LIMIT_REACHED',
                            'message' => "Device limit reached. Cannot register new device."
                        ], 403);
                    }
                }
                break;
        }

        return $next($request);
    }
}
