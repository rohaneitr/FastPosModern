<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\DeviceActivation;
use App\Modules\Tenant\Models\License;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeviceRegistrationService
 *
 * Extracted from DeviceHeartbeatController (all 308 lines) and
 * LicenseActivationController (all 167 lines).
 *
 * RESPONSIBILITIES:
 *   1. heartbeat()              — record device sync, detect fingerprint mismatch
 *   2. firstTimeRegistration()  — register new device with FIFO eviction
 *   3. getDeviceStatus()        — read-only device status check
 *   4. activatePosDevice()      — authenticated POS activation (DeviceActivation table)
 *   5. revokeDevice()           — revoke a specific device by ID
 *   6. activateDeviceByLicense() — license-key based activation (user_devices table)
 *   7. recordHeartbeatToken()   — extend Sanctum token on heartbeat
 *
 * ZERO TRUST:
 *   - business_id always comes from auth()->user() or the license record
 *   - Hardware fingerprint is never trusted without DB verification
 *   - FIFO device eviction uses lockForUpdate() to prevent race conditions
 *   - License key is truncated in all log entries (no plaintext secrets in logs)
 *
 * TWO-SYSTEM ARCHITECTURE (Brutal Honesty):
 *   There are two device systems in this codebase:
 *   A) device_activations table — used by DeviceHeartbeatController (license-key flow)
 *   B) user_devices table       — used by LicenseActivationController (Sanctum token flow)
 *   Both are preserved here. Merging them is a future architectural task.
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.5
 * @version 2026-06-12
 */
class DeviceRegistrationService
{
    // ── System A: device_activations (License-key heartbeat flow) ────────────

    /**
     * Process a device heartbeat. Validates the license, checks fingerprint,
     * and updates last_synced_at.
     *
     * @param string $licenseKey
     * @param string $hardwareHash
     *
     * @return array{status: string, code: string, data?: array, httpStatus: int}
     */
    public function heartbeat(string $licenseKey, string $hardwareHash): array
    {
        // ── 1. License validation ─────────────────────────────────────────────
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license || $license->status !== 'active') {
            $this->logRejection('crypto_invalid', $licenseKey, $hardwareHash, 'Invalid or suspended license');
            return [
                'httpStatus' => 401,
                'code'       => 'LICENSE_INVALID',
                'message'    => 'License verification failed.',
            ];
        }

        // ── 2. Expiry check ───────────────────────────────────────────────────
        if ($license->expires_at && now()->greaterThan($license->expires_at)) {
            $this->logRejection('token_expired', $licenseKey, $hardwareHash);
            return [
                'httpStatus' => 402,
                'code'       => 'LICENSE_EXPIRED',
                'message'    => 'License has expired. Please renew your subscription.',
            ];
        }

        // ── 3. Activation lookup ──────────────────────────────────────────────
        $activation = DeviceActivation::where('license_key', $licenseKey)->first();

        if (!$activation) {
            return $this->firstTimeRegistration($licenseKey, $hardwareHash, $license);
        }

        // ── 4. Revocation check ───────────────────────────────────────────────
        if ($activation->isRevoked()) {
            $this->logRejection('revoked', $licenseKey, $hardwareHash);
            return [
                'httpStatus' => 403,
                'code'       => 'LICENSE_REVOKED',
                'message'    => 'This license has been permanently revoked. Contact support.',
            ];
        }

        // ── 5. Fingerprint binding check ──────────────────────────────────────
        if ($activation->device_fingerprint && $activation->device_fingerprint !== $hardwareHash) {
            $this->logRejection('fingerprint_mismatch', $licenseKey, $hardwareHash, 'Mismatch');
            return [
                'httpStatus' => 403,
                'code'       => 'HARDWARE_MISMATCH',
                'message'    => 'Hardware fingerprint mismatch. This license is bound to a different device.',
            ];
        }

        // ── 6. Update heartbeat timestamp ─────────────────────────────────────
        DB::transaction(function () use ($activation, $hardwareHash) {
            $wasGraceExceeded = $activation->isGracePeriodExceeded();

            $activation->update([
                'device_fingerprint' => $hardwareHash,
                'last_synced_at'     => now(),
                'status'             => $activation->isRevoked()
                    ? DeviceActivation::STATUS_REVOKED
                    : DeviceActivation::STATUS_ACTIVE,
                'activated_at'       => $activation->activated_at ?? now(),
            ]);

            if ($wasGraceExceeded) {
                Log::info('DeviceHeartbeat: suspended device reconnected', [
                    'activation_id' => $activation->id,
                    'business_id'   => $activation->business_id,
                ]);
            }
        });

        $activation->refresh();

        return [
            'httpStatus'       => 200,
            'code'             => 'OK',
            'message'          => 'Heartbeat recorded. Device is active.',
            'last_synced_at'   => $activation->last_synced_at->toIso8601String(),
            'grace_period_days' => $activation->grace_period_days,
            'grace_expires_at' => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'   => $activation->gracePeriodDaysRemaining(),
            'token_expires_at' => $license->expires_at,
        ];
    }

    /**
     * First-time device registration with FIFO eviction policy.
     * Called when no DeviceActivation exists for this license_key.
     */
    public function firstTimeRegistration(string $licenseKey, string $hardwareHash, License $license): array
    {
        $tenantId = $license->tenant_id;
        $type     = 'web';

        $activation = DB::transaction(function () use ($licenseKey, $hardwareHash, $tenantId, $type, $license) {
            $limit       = $license->device_limit ?? 1;
            $activeCount = DeviceActivation::where('business_id', $tenantId)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->count();

            // FIFO eviction: if at limit, evict oldest device(s) first
            if ($activeCount >= $limit) {
                $evictCount = ($activeCount - $limit) + 1;
                $oldestIds  = DeviceActivation::where('business_id', $tenantId)
                    ->where('status', DeviceActivation::STATUS_ACTIVE)
                    ->orderBy('activated_at', 'asc')
                    ->limit($evictCount)
                    ->pluck('id');

                DeviceActivation::whereIn('id', $oldestIds)
                    ->update(['status' => DeviceActivation::STATUS_REVOKED]);

                Log::info('DeviceRegistration: FIFO eviction executed', [
                    'business_id' => $tenantId,
                    'evicted_ids' => $oldestIds->toArray(),
                ]);
            }

            return DeviceActivation::create([
                'business_id'        => $tenantId,
                'license_key'        => $licenseKey,
                'device_fingerprint' => $hardwareHash,
                'status'             => DeviceActivation::STATUS_ACTIVE,
                'activated_at'       => now(),
                'last_synced_at'     => now(),
                'grace_period_days'  => DeviceActivation::graceDaysForType($type),
            ]);
        });

        return [
            'httpStatus'        => 201,
            'code'              => 'REGISTERED',
            'message'           => 'Device registered and heartbeat recorded.',
            'last_synced_at'    => $activation->last_synced_at->toIso8601String(),
            'grace_period_days' => $activation->grace_period_days,
            'grace_expires_at'  => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'    => $activation->gracePeriodDaysRemaining(),
        ];
    }

    /**
     * Read-only status check for a device.
     */
    public function getDeviceStatus(string $licenseKey, string $hardwareHash): array
    {
        $activation = DeviceActivation::where('license_key', $licenseKey)->first();

        if (!$activation) {
            return ['httpStatus' => 404, 'code' => 'NOT_FOUND', 'message' => 'Device not registered.'];
        }

        if ($activation->isRevoked()) {
            return ['httpStatus' => 403, 'code' => 'LICENSE_REVOKED', 'message' => 'License revoked.', 'status' => 'revoked'];
        }

        if ($activation->device_fingerprint && $activation->device_fingerprint !== $hardwareHash) {
            return ['httpStatus' => 403, 'code' => 'HARDWARE_MISMATCH', 'message' => 'Hardware mismatch.'];
        }

        $gracePeriodExceeded = $activation->isGracePeriodExceeded();

        return [
            'httpStatus'        => 200,
            'status'            => $activation->status,
            'last_synced_at'    => $activation->last_synced_at?->toIso8601String(),
            'grace_period_days' => $activation->grace_period_days,
            'grace_expires_at'  => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'    => $activation->gracePeriodDaysRemaining(),
            'grace_exceeded'    => $gracePeriodExceeded,
            'code'              => $gracePeriodExceeded ? 'GRACE_EXCEEDED' : 'OK',
        ];
    }

    /**
     * Activate a POS device for an authenticated business (device_activations table).
     * Resolves license automatically or via explicit license_key.
     * Applies FIFO eviction if device limit reached.
     */
    public function activatePosDevice(int $businessId, string $hardwareHash, ?string $licenseKeyInput, ?string $deviceName): array
    {
        // ── Resolve license ───────────────────────────────────────────────────
        if ($licenseKeyInput) {
            $license = License::where('license_key', $licenseKeyInput)
                ->where('tenant_id', $businessId)
                ->first();

            if (!$license) {
                return ['httpStatus' => 403, 'code' => 'INVALID_LICENSE', 'message' => 'Invalid license key.'];
            }

            // Enforce single-active-license policy: suspend others, activate this one
            if ($license->status !== 'active') {
                License::where('tenant_id', $businessId)->update(['status' => 'suspended']);
                $license->update(['status' => 'active']);
            }
        } else {
            $license = License::where('tenant_id', $businessId)->where('status', 'active')->first();
        }

        if (!$license) {
            return ['httpStatus' => 403, 'code' => 'NO_LICENSE', 'message' => 'No active license found.'];
        }

        // ── Idempotency: already registered? ─────────────────────────────────
        $existing = DeviceActivation::where('device_fingerprint', $hardwareHash)
            ->where('business_id', $businessId)
            ->first();

        if ($existing) {
            if ($existing->isRevoked()) {
                return ['httpStatus' => 403, 'code' => 'DEVICE_REVOKED', 'message' => 'This device was revoked.'];
            }
            return ['httpStatus' => 200, 'code' => 'ALREADY_ACTIVE', 'message' => 'Device is already activated.', 'license_key' => $license->license_key];
        }

        // ── FIFO registration ─────────────────────────────────────────────────
        $limit = max(1, (int) ($license->device_limit ?? 1));

        DB::transaction(function () use ($license, $hardwareHash, $businessId, $limit) {
            $activeCount = DeviceActivation::where('business_id', $businessId)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->count();

            if ($activeCount >= $limit) {
                $evictCount = ($activeCount - $limit) + 1;
                $oldestIds  = DeviceActivation::where('business_id', $businessId)
                    ->where('status', DeviceActivation::STATUS_ACTIVE)
                    ->orderBy('activated_at', 'asc')
                    ->limit($evictCount)
                    ->pluck('id');

                DeviceActivation::whereIn('id', $oldestIds)
                    ->update(['status' => DeviceActivation::STATUS_REVOKED]);
            }

            DeviceActivation::create([
                'business_id'        => $businessId,
                'license_key'        => $license->license_key,
                'device_fingerprint' => $hardwareHash,
                'status'             => DeviceActivation::STATUS_ACTIVE,
                'activated_at'       => now(),
                'last_synced_at'     => now(),
                'grace_period_days'  => DeviceActivation::graceDaysForType('web'),
            ]);
        });

        return ['httpStatus' => 201, 'code' => 'ACTIVATED', 'message' => 'Device activated successfully', 'license_key' => $license->license_key];
    }

    /**
     * List all devices for a business.
     */
    public function listDevices(int $businessId): \Illuminate\Support\Collection
    {
        return DeviceActivation::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Revoke a specific device. Only BusinessAdmin can revoke.
     * Clears fingerprint to allow re-binding on a new device.
     */
    public function revokeDevice(int $businessId, int $deviceId): array
    {
        $device = DeviceActivation::where('id', $deviceId)
            ->where('business_id', $businessId)
            ->first();

        if (!$device) {
            return ['httpStatus' => 404, 'code' => 'NOT_FOUND', 'message' => 'Device not found.'];
        }

        $device->update([
            'status'             => DeviceActivation::STATUS_REVOKED,
            'device_fingerprint' => null, // Allow re-binding on a different device
        ]);

        return ['httpStatus' => 200, 'code' => 'REVOKED', 'message' => 'Device revoked successfully.'];
    }

    // ── System B: user_devices (Sanctum-token heartbeat flow) ────────────────

    /**
     * Activate a device via license_key → user_devices table → Sanctum token.
     * Used by the POS offline login flow.
     */
    public function activateDeviceByLicense(string $licenseKey, string $hardwareFingerprint, string $deviceName, string $ipAddress): array
    {
        return DB::transaction(function () use ($licenseKey, $hardwareFingerprint, $deviceName, $ipAddress) {
            $business = Business::where('license_key', $licenseKey)->first();

            if (!$business || !$business->is_active) {
                return ['httpStatus' => 403, 'code' => 'ACCOUNT_SUSPENDED', 'message' => 'Your account is temporarily suspended. Please contact support.'];
            }

            $subscription = Subscription::where('business_id', $business->id)->first();
            if (!$subscription || !$subscription->isActive()) {
                return ['httpStatus' => 403, 'code' => 'SUBSCRIPTION_EXPIRED', 'message' => 'Your subscription has expired. Please log into your Dashboard to renew.'];
            }

            // Resolve device limit
            $resolvedDeviceLimit = $subscription->resolved_device_limit ?? 0;
            $isUnlimited         = $resolvedDeviceLimit <= 0 || $resolvedDeviceLimit === -1;

            $ownerUser = User::where('business_id', $business->id)->first();
            if (!$ownerUser) {
                return ['httpStatus' => 500, 'code' => 'NO_OWNER', 'message' => 'Business owner not found.'];
            }

            // Idempotency: check if device already registered
            $existingDevice = DB::table('user_devices')
                ->where('user_id', $ownerUser->id)
                ->where('browser', $hardwareFingerprint)
                ->first();

            if ($existingDevice) {
                if ($existingDevice->status === 'revoked') {
                    return ['httpStatus' => 403, 'code' => 'DEVICE_REVOKED', 'message' => 'This device has been permanently revoked and cannot be reactivated.'];
                }
                DB::table('user_devices')->where('id', $existingDevice->id)->update([
                    'device_name' => $deviceName,
                    'status'      => 'active',
                    'last_login'  => now(),
                    'updated_at'  => now(),
                ]);
            } else {
                // Enforce device limit
                $activeDevicesCount = DB::table('user_devices')
                    ->join('users', 'user_devices.user_id', '=', 'users.id')
                    ->where('users.business_id', $business->id)
                    ->where('user_devices.status', 'active')
                    ->count();

                if (!$isUnlimited && $activeDevicesCount >= $resolvedDeviceLimit) {
                    return [
                        'httpStatus' => 422,
                        'code'       => 'DEVICE_LIMIT_REACHED',
                        'message'    => "You have reached your maximum limit of {$resolvedDeviceLimit} devices. Please revoke an old device or upgrade your plan.",
                    ];
                }

                DB::table('user_devices')->insert([
                    'user_id'      => $ownerUser->id,
                    'device_name'  => $deviceName,
                    'browser'      => $hardwareFingerprint,
                    'os'           => 'POS Native',
                    'ip_address'   => $ipAddress,
                    'status'       => 'active',
                    'session_type' => 'offline_pos',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Generate 72-hour offline Sanctum token
            $tokenResult = $ownerUser->createToken(
                'POS_Offline_Heartbeat_' . $hardwareFingerprint,
                ['pos:sync', 'pos:offline'],
                now()->addHours(72),
            );

            AuditLogger::log(
                $business->id, $ownerUser, 'device_activated',
                Business::class, $business->id, [],
                ['device_name' => $deviceName, 'fingerprint' => $hardwareFingerprint, 'message' => "Device {$deviceName} activated under License."]
            );

            return [
                'httpStatus' => 201,
                'code'       => 'ACTIVATED',
                'message'    => 'Device activated successfully',
                'token'      => $tokenResult->plainTextToken,
                'expires_at' => now()->addHours(72)->toIso8601String(),
                'business'   => ['id' => $business->id, 'name' => $business->name],
            ];
        });
    }

    /**
     * Heartbeat for the Sanctum-token flow (user_devices table).
     * Extends the token by 72 hours on each successful heartbeat.
     */
    public function recordHeartbeatToken(User $user): array
    {
        $token       = $user->currentAccessToken();
        $fingerprint = str_replace('POS_Offline_Heartbeat_', '', $token->name);

        $device = DB::table('user_devices')
            ->where('user_id', $user->id)
            ->where('browser', $fingerprint)
            ->first();

        $business     = Business::find($user->business_id);
        $subscription = Subscription::where('business_id', $business?->id)->first();

        if (!$device || $device->status !== 'active' || !$business?->is_active || !$subscription?->isActive()) {
            $token->delete(); // Force logout — revoke token immediately
            return [
                'httpStatus' => 403,
                'code'       => 'FORCE_LOGOUT',
                'directive'  => 'FORCE_LOGOUT',
                'message'    => 'Your account is temporarily suspended, plan expired, or this device was revoked.',
            ];
        }

        DB::table('user_devices')->where('id', $device->id)->update([
            'last_login' => now(),
            'updated_at' => now(),
        ]);

        // Extend token lifetime
        $token->forceFill(['expires_at' => now()->addHours(72)])->save();

        return [
            'httpStatus' => 200,
            'code'       => 'OK',
            'status'     => 'active',
            'expires_at' => now()->addHours(72)->toIso8601String(),
            'message'    => 'Heartbeat acknowledged. Session extended.',
        ];
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Log a rejected heartbeat attempt.
     * License key and hardware hash are truncated to prevent secret leakage in logs.
     */
    private function logRejection(string $reason, string $licenseKey, string $hardwareHash, ?string $detail = null): void
    {
        Log::warning('DeviceHeartbeat: rejected', [
            'reason'        => $reason,
            'license_key'   => substr($licenseKey, 0, 20) . '…',
            'hardware_hash' => substr($hardwareHash, 0, 12) . '…',
            'detail'        => $detail,
            'ip'            => request()->ip(),
        ]);
    }
}
