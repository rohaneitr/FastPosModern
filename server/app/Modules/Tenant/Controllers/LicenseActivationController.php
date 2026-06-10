<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LicenseActivationController extends Controller
{
    /**
     * POST /api/v1/licenses/activate-device
     */
    public function activateDevice(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'hardware_fingerprint' => 'required|string',
            'device_name' => 'required|string',
        ]);

        return DB::transaction(function() use ($request) {
            $business = Business::where('license_key', $request->license_key)->firstOrFail();
            
            if (!$business || !$business->is_active) {
                return response()->json(['message' => 'Your account is temporarily suspended. Please contact support.'], 403);
            }

            $subscription = Subscription::where('business_id', $business->id)->first();
            if (!$subscription || !$subscription->isActive()) {
                return response()->json(['message' => 'Your subscription has expired. Please log into your Dashboard to renew.'], 403);
            }

            // Check device limits
            $resolvedDeviceLimit = $subscription->resolved_device_limit ?? 0;
            $isUnlimited = $resolvedDeviceLimit <= 0 || $resolvedDeviceLimit === -1;

            $ownerUser = User::where('business_id', $business->id)->first();
            if (!$ownerUser) {
                return response()->json(['message' => 'Business owner not found.'], 500);
            }

            // Check if device is already registered under this owner
            $existingDevice = DB::table('user_devices')
                ->where('user_id', $ownerUser->id)
                ->where('browser', $request->hardware_fingerprint) // using browser field for fingerprint
                ->first();

            if ($existingDevice) {
                if ($existingDevice->status === 'revoked') {
                    return response()->json(['message' => 'This device has been permanently revoked and cannot be reactivated.'], 403);
                }

                DB::table('user_devices')->where('id', $existingDevice->id)->update([
                    'device_name' => $request->device_name,
                    'status' => 'active',
                    'last_login' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $activeDevicesCount = DB::table('user_devices')
                    ->join('users', 'user_devices.user_id', '=', 'users.id')
                    ->where('users.business_id', $business->id)
                    ->where('user_devices.status', 'active')
                    ->count();

                if (!$isUnlimited && $activeDevicesCount >= $resolvedDeviceLimit) {
                    return response()->json(['message' => "You have reached your maximum limit of {$resolvedDeviceLimit} devices. Please log into your Tenant Dashboard to revoke an old device or upgrade your plan."], 422);
                }

                $deviceId = DB::table('user_devices')->insertGetId([
                    'user_id' => $ownerUser->id,
                    'device_name' => $request->device_name,
                    'browser' => $request->hardware_fingerprint,
                    'os' => 'POS Native',
                    'ip_address' => $request->ip(),
                    'status' => 'active',
                    'session_type' => 'offline_pos',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Generate short-lived Sanctum Token (72 hours max offline limit)
            $tokenResult = $ownerUser->createToken(
                'POS_Offline_Heartbeat_' . $request->hardware_fingerprint, 
                ['pos:sync', 'pos:offline'], 
                now()->addHours(72)
            );

            \App\Modules\Tenant\Services\AuditLogger::log(
                $business->id,
                $ownerUser,
                'device_activated',
                'App\Modules\Tenant\Models\Business',
                $business->id,
                [],
                ['device_name' => $request->device_name, 'fingerprint' => $request->hardware_fingerprint, 'message' => "Device {$request->device_name} activated under License."]
            );

            return response()->json([
                'message' => 'Device activated successfully',
                'token' => $tokenResult->plainTextToken,
                'expires_at' => now()->addHours(72)->toIso8601String(),
                'business' => [
                    'id' => $business->id,
                    'name' => $business->name,
                ]
            ]);
        });
    }

    /**
     * Phase 1: The Heartbeat Sync API (Anti-Hack Completion)
     */
    public function heartbeat(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        // Extract hardware fingerprint from token name: POS_Offline_Heartbeat_{fingerprint}
        $fingerprint = str_replace('POS_Offline_Heartbeat_', '', $token->name);

        $device = DB::table('user_devices')
            ->where('user_id', $user->id)
            ->where('browser', $fingerprint)
            ->first();

        $business = Business::find($user->business_id);
        $subscription = Subscription::where('business_id', $business->id)->first();

        if (
            !$device || $device->status !== 'active' ||
            !$business || !$business->is_active ||
            !$subscription || !$subscription->isActive()
        ) {
            // Force logout and purge token
            $token->delete();
            return response()->json([
                'directive' => 'FORCE_LOGOUT', 
                'message' => 'Your account is temporarily suspended, plan expired, or this device was revoked. Please contact support or check your dashboard.'
            ], 403);
        }

        // Update last login (heartbeat timestamp)
        DB::table('user_devices')->where('id', $device->id)->update([
            'last_login' => now(),
            'updated_at' => now()
        ]);

        // Fix Network Trap: Safely extend the existing token instead of deleting it
        $token->forceFill(['expires_at' => now()->addHours(72)])->save();

        return response()->json([
            'status' => 'active',
            'token' => $token->name, // Return name or don't return plainTextToken as we extended it
            'expires_at' => now()->addHours(72)->toIso8601String(),
            'message' => 'Heartbeat acknowledged. Session extended.'
        ]);
    }
}
