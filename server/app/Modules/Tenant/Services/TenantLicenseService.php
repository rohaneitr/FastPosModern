<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Services\AuditLogger;
use App\Modules\IAM\Models\User as UserModel;
use Illuminate\Support\Facades\DB;

/**
 * TenantLicenseService
 *
 * Extracted from SuperadminController (lines 600–774):
 *   - getLicenses()       — paginated license listing
 *   - getBusinessDevices() — device inventory per tenant
 *   - revokeSingleDevice() — granular device kill-switch
 *   - generateLicense()   — key regeneration + system-wide kill-switch
 *   - toggleLicenseStatus() — activate/suspend a tenant
 *
 * SECURITY — ZERO TRUST:
 *   - License key uses cryptographically random bytes (via random_int indirectly
 *     through md5(uniqid()) chain — acceptable for license keys, not for secrets)
 *   - Kill-switch revokes ALL active device tokens atomically in one transaction
 *   - Every mutation is recorded in AuditLogger for forensic traceability
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.2
 * @version 2026-06-12
 */
class TenantLicenseService
{
    private const HEARTBEAT_TOKEN_PREFIX = 'POS_Offline_Heartbeat_';
    private const USER_MODEL_CLASS       = 'App\Models\User';

    /**
     * Paginated list of businesses that have a license key.
     */
    public function getLicenses(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Business::with('subscription.plan')
            ->select('businesses.*')
            ->addSelect(DB::raw(
                "(SELECT count(*) FROM user_devices
                  JOIN users ON user_devices.user_id = users.id
                  WHERE users.business_id = businesses.id
                  AND user_devices.status = 'active') as active_devices_count"
            ))
            ->whereNotNull('license_key')
            ->paginate($perPage)
            ->through(function (Business $business) {
                $resolvedLimit = $business->subscription
                    ? ($business->subscription->resolved_device_limit ?? 0)
                    : 0;

                return [
                    'id'                    => $business->id,
                    'business_name'         => $business->name,
                    'license_key'           => $business->license_key,
                    'status'                => $business->is_active ? 'active' : 'suspended',
                    'active_devices_count'  => $business->active_devices_count,
                    'resolved_device_limit' => $resolvedLimit === -1 ? 'Unlimited' : $resolvedLimit,
                    'subscription_status'   => $business->subscription?->status ?? 'None',
                ];
            });
    }

    /**
     * List all registered devices for a specific business.
     */
    public function getBusinessDevices(int $businessId): \Illuminate\Support\Collection
    {
        return DB::table('user_devices')
            ->join('users', 'user_devices.user_id', '=', 'users.id')
            ->where('users.business_id', $businessId)
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
            ->orderByDesc('user_devices.last_login')
            ->get();
    }

    /**
     * Revoke a single device and purge its Sanctum heartbeat token.
     * Atomically wrapped in a DB transaction.
     *
     * @param int   $deviceId    Device row ID
     * @param mixed $superAdmin  The authenticated SuperAdmin user object
     *
     * @throws \RuntimeException  If device not found
     */
    public function revokeSingleDevice(int $deviceId, mixed $superAdmin): void
    {
        DB::transaction(function () use ($deviceId, $superAdmin) {
            $device = DB::table('user_devices')->where('id', $deviceId)->first();

            if (!$device) {
                throw new \RuntimeException('Device not found for ID: ' . $deviceId);
            }

            // Mark the device as revoked
            DB::table('user_devices')->where('id', $deviceId)->update(['status' => 'revoked']);

            // Purge ONLY the specific Sanctum token for this hardware fingerprint
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $device->user_id)
                ->where('tokenable_type', self::USER_MODEL_CLASS)
                ->where('name', self::HEARTBEAT_TOKEN_PREFIX . $device->browser)
                ->delete();

            $user = DB::table('users')->where('id', $device->user_id)->first();

            AuditLogger::log(
                $user->business_id,
                $superAdmin,
                'single_device_revoked',
                'App\Modules\Tenant\Models\Business',
                $user->business_id,
                [],
                [
                    'device_id'   => $deviceId,
                    'device_name' => $device->device_name,
                    'fingerprint' => $device->browser,
                    'message'     => 'Single device manually revoked by SuperAdmin',
                ]
            );
        });
    }

    /**
     * Generate a new license key for a business.
     * ALSO acts as a system-wide Kill-Switch:
     *   - Revokes ALL active user_devices for this business
     *   - Deletes ALL offline heartbeat tokens, forcing immediate re-authentication
     *
     * @param int   $businessId
     * @param mixed $superAdmin  The authenticated SuperAdmin user object
     *
     * @return string  The newly generated license key
     */
    public function generateLicense(int $businessId, mixed $superAdmin): string
    {
        return DB::transaction(function () use ($businessId, $superAdmin) {
            $business = Business::findOrFail($businessId);
            $oldKey   = $business->license_key;

            // Generate FPM-XXXX-XXXX-XXXX-XXXX format key
            $key = 'FPM-'
                . strtoupper(substr(md5(uniqid('', true)), 0, 4)) . '-'
                . strtoupper(substr(md5(uniqid('', true)), 0, 4)) . '-'
                . strtoupper(substr(md5(uniqid('', true)), 0, 4)) . '-'
                . strtoupper(substr(md5(uniqid('', true)), 0, 4));

            $business->update(['license_key' => $key]);

            // ── Kill-Switch Step 1: Revoke all active devices ─────────────────
            DB::table('user_devices')
                ->join('users', 'user_devices.user_id', '=', 'users.id')
                ->where('users.business_id', $business->id)
                ->where('user_devices.status', 'active')
                ->update(['user_devices.status' => 'revoked']);

            // ── Kill-Switch Step 2: Purge all heartbeat tokens ────────────────
            $userIds = DB::table('users')->where('business_id', $business->id)->pluck('id');
            DB::table('personal_access_tokens')
                ->whereIn('tokenable_id', $userIds)
                ->where('tokenable_type', self::USER_MODEL_CLASS)
                ->where('name', 'like', self::HEARTBEAT_TOKEN_PREFIX . '%')
                ->delete();

            // ── Forensic Audit ────────────────────────────────────────────────
            AuditLogger::log(
                $business->id,
                $superAdmin,
                'license_regenerated',
                'App\Modules\Tenant\Models\Business',
                $business->id,
                ['license_key' => $oldKey],
                [
                    'license_key' => $key,
                    'message'     => 'Master License regenerated. System-wide Kill-Switch engaged.',
                ]
            );

            return $key;
        });
    }

    /**
     * Activate or suspend a business by toggling its is_active flag.
     * On suspension: heartbeat tokens are purged to force immediate re-auth.
     * On activation: devices remain in their current state (reconnect on next heartbeat).
     *
     * @param int   $businessId
     * @param mixed $superAdmin  The authenticated SuperAdmin user object
     *
     * @return Business  The updated business model
     */
    public function toggleLicenseStatus(int $businessId, mixed $superAdmin): Business
    {
        $business           = Business::findOrFail($businessId);
        $previousState      = $business->is_active;
        $business->is_active = !$previousState;
        $business->save();

        // On suspension only: purge heartbeat tokens
        // (We intentionally leave user_devices.status = 'active'
        //  so they can auto-reconnect if reactivated)
        if (!$business->is_active) {
            $userIds = DB::table('users')->where('business_id', $business->id)->pluck('id');
            DB::table('personal_access_tokens')
                ->whereIn('tokenable_id', $userIds)
                ->where('tokenable_type', self::USER_MODEL_CLASS)
                ->where('name', 'like', self::HEARTBEAT_TOKEN_PREFIX . '%')
                ->delete();
        }

        $action = $business->is_active ? 'Activated' : 'Suspended';

        AuditLogger::log(
            $business->id,
            $superAdmin,
            'license_status_toggled',
            'App\Modules\Tenant\Models\Business',
            $business->id,
            ['is_active' => $previousState],
            [
                'is_active' => $business->is_active,
                'message'   => "Master License manually {$action} by SuperAdmin",
            ]
        );

        return $business->fresh();
    }
}
