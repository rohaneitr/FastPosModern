<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\DeviceActivation;
use App\Modules\Tenant\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceHeartbeatController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'   => 'required|string',
            'hardware_hash' => 'required|string|min:8|max:512',
        ]);

        $licenseKey   = $request->license_key;
        $hardwareHash = $request->hardware_hash;

        $license = License::where('license_key', $licenseKey)->first();
        if (!$license || $license->status !== 'active') {
            $this->logFailure('crypto_invalid', $licenseKey, $hardwareHash, 'Invalid or suspended license');
            return response()->json([
                'message' => 'License verification failed.',
                'code'    => 'LICENSE_INVALID',
            ], 401);
        }

        if ($license->expires_at && now()->greaterThan($license->expires_at)) {
            $this->logFailure('token_expired', $licenseKey, $hardwareHash);
            return response()->json([
                'message' => 'License has expired. Please renew your subscription.',
                'code'    => 'LICENSE_EXPIRED',
            ], 402);
        }

        $activation = DeviceActivation::where('license_key', $licenseKey)->first();

        if (!$activation) {
            return $this->firstTimeRegistration($licenseKey, $hardwareHash, $license);
        }

        if ($activation->isRevoked()) {
            $this->logFailure('revoked', $licenseKey, $hardwareHash);
            return response()->json([
                'message' => 'This license has been permanently revoked. Contact support.',
                'code'    => 'LICENSE_REVOKED',
            ], 403);
        }

        if ($activation->device_fingerprint && $activation->device_fingerprint !== $hardwareHash) {
            $this->logFailure('fingerprint_mismatch', $licenseKey, $hardwareHash, 'Mismatch');
            return response()->json([
                'message' => 'Hardware fingerprint mismatch. This license is bound to a different device.',
                'code'    => 'HARDWARE_MISMATCH',
            ], 403);
        }

        DB::transaction(function () use ($activation, $hardwareHash) {
            $wasGraceExceeded = $activation->isGracePeriodExceeded();

            $activation->update([
                'device_fingerprint' => $hardwareHash,
                'last_synced_at'     => now(),
                'status' => $activation->isRevoked() ? DeviceActivation::STATUS_REVOKED : DeviceActivation::STATUS_ACTIVE,
                'activated_at' => $activation->activated_at ?? now(),
            ]);

            if ($wasGraceExceeded) {
                Log::info('DeviceHeartbeat: suspended device reconnected', [
                    'activation_id' => $activation->id,
                    'business_id'   => $activation->business_id,
                ]);
            }
        });

        $activation->refresh();

        return response()->json([
            'message'              => 'Heartbeat recorded. Device is active.',
            'code'                 => 'OK',
            'last_synced_at'       => $activation->last_synced_at->toIso8601String(),
            'grace_period_days'    => $activation->grace_period_days,
            'grace_expires_at'     => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'       => $activation->gracePeriodDaysRemaining(),
            'token_expires_at'     => $license->expires_at,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'   => 'required|string',
            'hardware_hash' => 'required|string',
        ]);

        $activation = DeviceActivation::where('license_key', $request->license_key)->first();

        if (!$activation) {
            return response()->json(['message' => 'Device not registered.', 'code' => 'NOT_FOUND'], 404);
        }

        if ($activation->isRevoked()) {
            return response()->json(['message' => 'License revoked.', 'code' => 'LICENSE_REVOKED', 'status' => 'revoked'], 403);
        }

        if ($activation->device_fingerprint && $activation->device_fingerprint !== $request->hardware_hash) {
            return response()->json(['message' => 'Hardware mismatch.', 'code' => 'HARDWARE_MISMATCH'], 403);
        }

        $gracePeriodExceeded = $activation->isGracePeriodExceeded();

        return response()->json([
            'status'            => $activation->status,
            'last_synced_at'    => $activation->last_synced_at?->toIso8601String(),
            'grace_period_days' => $activation->grace_period_days,
            'grace_expires_at'  => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'    => $activation->gracePeriodDaysRemaining(),
            'grace_exceeded'    => $gracePeriodExceeded,
            'code'              => $gracePeriodExceeded ? 'GRACE_EXCEEDED' : 'OK',
        ]);
    }

    private function firstTimeRegistration(string $licenseKey, string $hardwareHash, License $license): JsonResponse
    {
        $tenantId = $license->tenant_id;
        $type = 'web';

        $activation = DB::transaction(function () use ($licenseKey, $hardwareHash, $tenantId, $type, $license) {
            $limit = $license->device_limit ?? 1;

            $activeCount = DeviceActivation::where('business_id', $tenantId)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->count();

            if ($activeCount >= $limit) {
                // FIFO DEVICE REVOCATION
                $evictCount = ($activeCount - $limit) + 1;
                $oldestIds  = DeviceActivation::where('business_id', $tenantId)
                    ->where('status', DeviceActivation::STATUS_ACTIVE)
                    ->orderBy('activated_at', 'asc')
                    ->limit($evictCount)
                    ->pluck('id');

                DeviceActivation::whereIn('id', $oldestIds)
                    ->update(['status' => DeviceActivation::STATUS_REVOKED]);
            }

            return DeviceActivation::create([
                'business_id'       => $tenantId,
                'license_key'       => $licenseKey,
                'device_fingerprint'=> $hardwareHash,
                'status'            => DeviceActivation::STATUS_ACTIVE,
                'activated_at'      => now(),
                'last_synced_at'    => now(),
                'grace_period_days' => DeviceActivation::graceDaysForType($type),
            ]);
        });

        return response()->json([
            'message'           => 'Device registered and heartbeat recorded.',
            'code'              => 'REGISTERED',
            'last_synced_at'    => $activation->last_synced_at->toIso8601String(),
            'grace_period_days' => $activation->grace_period_days,
            'grace_expires_at'  => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'    => $activation->gracePeriodDaysRemaining(),
        ], 201);
    }

    public function activatePosDevice(Request $request): JsonResponse
    {
        $request->validate([
            'hardware_hash' => 'required|string|min:8|max:512',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $businessId = $user->business_id;
        $licenseKeyInput = $request->input('license_key');

        if ($licenseKeyInput) {
            $license = License::where('license_key', $licenseKeyInput)
                ->where('tenant_id', $businessId)
                ->first();
            if (!$license) {
                return response()->json(['message' => 'Invalid license key.'], 403);
            }
            if ($license->status !== 'active') {
                License::where('tenant_id', $businessId)->update(['status' => 'suspended']);
                $license->update(['status' => 'active']);
            }
        } else {
            $license = License::where('tenant_id', $businessId)->where('status', 'active')->first();
        }

        if (!$license) {
            return response()->json(['message' => 'No active license found.'], 403);
        }

        $hardwareHash = $request->hardware_hash;

        $existing = DeviceActivation::where('device_fingerprint', $hardwareHash)
            ->where('business_id', $businessId)
            ->first();

        if ($existing) {
            if ($existing->isRevoked()) {
                return response()->json(['message' => 'This device was revoked.'], 403);
            }
            return response()->json([
                'message' => 'Device is already activated.',
                'license_key' => $license->license_key
            ]);
        }

        $limit = max(1, (int) ($license->device_limit ?? 1));

        $activation = DB::transaction(function () use ($license, $hardwareHash, $businessId, $limit) {
            $activeCount = DeviceActivation::where('business_id', $businessId)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->count();

            if ($activeCount >= $limit) {
                // FIFO DEVICE REVOCATION
                $evictCount = ($activeCount - $limit) + 1;
                $oldestIds  = DeviceActivation::where('business_id', $businessId)
                    ->where('status', DeviceActivation::STATUS_ACTIVE)
                    ->orderBy('activated_at', 'asc')
                    ->limit($evictCount)
                    ->pluck('id');

                DeviceActivation::whereIn('id', $oldestIds)
                    ->update(['status' => DeviceActivation::STATUS_REVOKED]);
            }

            return DeviceActivation::create([
                'business_id'        => $businessId,
                'license_key'        => $license->license_key,
                'device_fingerprint' => $hardwareHash,
                'status'             => DeviceActivation::STATUS_ACTIVE,
                'activated_at'       => now(),
                'last_synced_at'     => now(),
                'grace_period_days'  => DeviceActivation::graceDaysForType('web'),
            ]);
        });

        return response()->json([
            'message'     => 'Device activated successfully',
            'license_key' => $license->license_key
        ], 201);
    }

    public function getDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $devices = DeviceActivation::where('business_id', $user->business_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($devices);
    }

    public function revokeDevice(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->business_id || !$user->hasRole('BusinessAdmin')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = DeviceActivation::where('id', $id)
            ->where('business_id', $user->business_id)
            ->firstOrFail();

        $device->update([
            'status' => DeviceActivation::STATUS_REVOKED,
            'device_fingerprint' => null
        ]);

        return response()->json(['message' => 'Device revoked successfully.']);
    }

    private function logFailure(string $reason, string $licenseKey, string $hardwareHash, ?string $detail = null): void
    {
        Log::warning('DeviceHeartbeat: rejected', [
            'reason'       => $reason,
            'license_key'  => substr($licenseKey, 0, 20) . '…',
            'hardware_hash'=> substr($hardwareHash, 0, 12) . '…',
            'detail'       => $detail,
            'ip'           => request()->ip(),
        ]);
    }
}
