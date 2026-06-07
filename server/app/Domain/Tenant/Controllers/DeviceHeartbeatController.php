<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Tenant\Models\DeviceActivation;
use App\Domain\Tenant\Models\License;
use App\Domain\Tenant\Services\LicenseKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * DeviceHeartbeatController  (Phase 3 – Gate 2 Hardware Binding)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * POST /api/v1/devices/heartbeat
 *
 * Called by every Hybrid / Mobile client on startup and at a regular interval
 * (recommended: every 24 h). The endpoint:
 *
 *   1. Decodes & cryptographically verifies the ECDSA license token.
 *   2. Checks the token has not expired.
 *   3. Looks up the DeviceActivation row by license_key.
 *   4. Validates the incoming hardware_hash matches the registered fingerprint.
 *   5. Updates last_synced_at → now() and clears suspension if within grace.
 *   6. Returns the remaining grace-period days so the client can warn the user.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DeviceHeartbeatController extends Controller
{
    public function __construct(private readonly LicenseKeyService $licenseService)
    {
    }

    // ─── POST /api/v1/devices/heartbeat ───────────────────────────────────────

    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'   => 'required|string',
            'hardware_hash' => 'required|string|min:8|max:512',
        ]);

        $licenseKey   = $request->license_key;
        $hardwareHash = $request->hardware_hash;

        // ── 1. Cryptographic verification ─────────────────────────────────────
        $verification = $this->licenseService->verifyLicense($licenseKey);

        if (!$verification['valid']) {
            $this->logFailure('crypto_invalid', $licenseKey, $hardwareHash, $verification['error']);
            return response()->json([
                'message' => 'License verification failed: ' . $verification['error'],
                'code'    => 'LICENSE_INVALID',
            ], 401);
        }

        if ($verification['expired']) {
            $this->logFailure('token_expired', $licenseKey, $hardwareHash);
            return response()->json([
                'message' => 'License token has expired. Please renew your subscription.',
                'code'    => 'LICENSE_EXPIRED',
            ], 402);
        }

        $payload = $verification['payload'];

        // ── 2. Look up the DeviceActivation record ────────────────────────────
        $activation = DeviceActivation::where('license_key', $licenseKey)->first();

        if (!$activation) {
            // First heartbeat from this device — auto-register it.
            // The device limit check is done here to prevent over-registration.
            return $this->firstTimeRegistration($licenseKey, $hardwareHash, $payload);
        }

        // ── 3. Hard block: revoked devices can never recover via heartbeat ────
        if ($activation->isRevoked()) {
            $this->logFailure('revoked', $licenseKey, $hardwareHash);
            return response()->json([
                'message' => 'This license has been permanently revoked. Contact support.',
                'code'    => 'LICENSE_REVOKED',
            ], 403);
        }

        // ── 4. Hardware binding check ─────────────────────────────────────────
        if ($activation->device_fingerprint && $activation->device_fingerprint !== $hardwareHash) {
            $this->logFailure('fingerprint_mismatch', $licenseKey, $hardwareHash, sprintf(
                'Expected %s, got %s',
                substr($activation->device_fingerprint, 0, 8) . '…',
                substr($hardwareHash, 0, 8) . '…'
            ));
            return response()->json([
                'message' => 'Hardware fingerprint mismatch. This license is bound to a different device.',
                'code'    => 'HARDWARE_MISMATCH',
            ], 403);
        }

        // ── 5. Update heartbeat & potentially lift suspension ─────────────────
        DB::transaction(function () use ($activation, $hardwareHash) {
            $wasGraceExceeded = $activation->isGracePeriodExceeded();

            $activation->update([
                'device_fingerprint' => $hardwareHash,           // bind/confirm fingerprint
                'last_synced_at'     => now(),
                // If device was suspended due to grace period (not revoked),
                // restore to active now that it has reconnected.
                'status' => $activation->isRevoked()
                    ? DeviceActivation::STATUS_REVOKED
                    : DeviceActivation::STATUS_ACTIVE,
                'activated_at' => $activation->activated_at ?? now(),
            ]);

            if ($wasGraceExceeded) {
                Log::info('DeviceHeartbeat: suspended device reconnected', [
                    'activation_id' => $activation->id,
                    'business_id'   => $activation->business_id,
                ]);
            }
        });

        // Reload to get fresh last_synced_at
        $activation->refresh();

        return response()->json([
            'message'              => 'Heartbeat recorded. Device is active.',
            'code'                 => 'OK',
            'last_synced_at'       => $activation->last_synced_at->toIso8601String(),
            'grace_period_days'    => $activation->grace_period_days,
            'grace_expires_at'     => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'       => $activation->gracePeriodDaysRemaining(),
            'token_expires_at'     => $payload['expires_at'] ?? null,
        ]);
    }

    // ─── POST /api/v1/devices/status ─────────────────────────────────────────

    /**
     * Lightweight status check — does NOT update last_synced_at.
     * Useful for the mobile client to show offline warnings without
     * counting as a full heartbeat.
     */
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

    // ─── First-time registration ──────────────────────────────────────────────

    private function firstTimeRegistration(string $licenseKey, string $hardwareHash, array $payload): JsonResponse
    {
        $tenantId = $payload['tenant_id'] ?? null;
        $type     = $payload['type']      ?? 'web';

        if (!$tenantId) {
            return response()->json(['message' => 'Invalid license payload.', 'code' => 'BAD_PAYLOAD'], 422);
        }

        // Verify the License record exists and belongs to this tenant
        $license = License::where('license_key', $licenseKey)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$license) {
            return response()->json([
                'message' => 'License not found or inactive.',
                'code'    => 'LICENSE_NOT_FOUND',
            ], 404);
        }

        // Enforce device limit with pessimistic lock
        $activation = DB::transaction(function () use ($licenseKey, $hardwareHash, $tenantId, $type, $license) {
            $activeCount = DeviceActivation::where('business_id', $tenantId)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->count();

            if ($activeCount >= ($license->device_limit ?? 1)) {
                return null; // Signal quota exceeded
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

        if ($activation === null) {
            return response()->json([
                'message' => 'Device quota exceeded for this license.',
                'code'    => 'QUOTA_EXCEEDED',
            ], 403);
        }

        Log::info('DeviceHeartbeat: new device registered', [
            'business_id' => $tenantId,
            'type'        => $type,
            'activation_id' => $activation->id,
        ]);

        return response()->json([
            'message'           => 'Device registered and heartbeat recorded.',
            'code'              => 'REGISTERED',
            'last_synced_at'    => $activation->last_synced_at->toIso8601String(),
            'grace_period_days' => $activation->grace_period_days,
            'grace_expires_at'  => $activation->gracePeriodExpiresAt()?->toIso8601String(),
            'days_remaining'    => $activation->gracePeriodDaysRemaining(),
        ], 201);
    }

    // ─── POS Specific Activation & Management ─────────────────────────────────

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
            // Get the active license for this business
            $license = License::where('tenant_id', $businessId)
                ->where('status', 'active')
                ->first();
        }

        if (!$license) {
            // Lazy provision a license if the tenant has an active subscription
            $subscription = \App\Domain\Tenant\Models\Subscription::with('plan')->where('business_id', $businessId)->where('status', 'active')->first();
            if ($subscription && $subscription->plan) {
                $provisionAction = new \App\Domain\Tenant\Actions\ProvisionSubscriptionAction();
                $result = $provisionAction->execute($businessId, $subscription->plan, $subscription->current_period_end, $request->hardware_hash);
                
                if (!empty($result['license_key'])) {
                    $license = License::where('license_key', $result['license_key'])->first();
                } else {
                    return response()->json(['message' => 'Failed to provision license.'], 500);
                }
            } else {
                return response()->json(['message' => 'No active license or subscription found.'], 403);
            }
        }

        $hardwareHash = $request->hardware_hash;

        // Check if already registered
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

        // Add a feature to force-release/clear old device sessions
        if ($request->boolean('force_release')) {
            DeviceActivation::where('business_id', $businessId)
                ->where('license_key', $license->license_key)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->update(['status' => DeviceActivation::STATUS_REVOKED]);
        }

        // Register new
        $activation = DB::transaction(function () use ($license, $hardwareHash, $businessId) {
            $activeCount = DeviceActivation::where('business_id', $businessId)
                ->where('status', DeviceActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->count();

            // Check against License Key metadata limit
            if ($activeCount >= ($license->device_limit ?? 1)) {
                return null;
            }

            return DeviceActivation::create([
                'business_id' => $businessId,
                'license_key' => $license->license_key,
                'device_fingerprint' => $hardwareHash,
                'status' => DeviceActivation::STATUS_ACTIVE,
                'activated_at' => now(),
                'last_synced_at' => now(),
                'grace_period_days' => DeviceActivation::graceDaysForType('web'),
            ]);
        });

        if (!$activation) {
            return response()->json([
                'message' => 'Device Limit Reached. Cannot activate more POS devices.',
                'code' => 'QUOTA_EXCEEDED'
            ], 403);
        }

        return response()->json([
            'message' => 'Device activated successfully',
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
            'device_fingerprint' => null // clear to prevent reuse
        ]);

        return response()->json(['message' => 'Device revoked successfully.']);
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

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
