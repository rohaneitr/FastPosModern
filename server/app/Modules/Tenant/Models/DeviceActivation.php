<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * DeviceActivation — Eloquent Model
 *
 * Represents a single POS device registration against a License.
 * Tracks the device fingerprint, grace period, and revocation status.
 *
 * COLUMNS (device_activations table):
 *   id                 bigint PK
 *   business_id        bigint FK → businesses.id  (tenant isolation)
 *   license_key        string   FK → licenses.license_key
 *   device_fingerprint string   Hardware hash (nullable until first heartbeat)
 *   status             string   STATUS_* constant
 *   activated_at       timestamp
 *   last_synced_at     timestamp
 *   grace_period_days  int      Offline grace window
 *   created_at / updated_at
 *
 * UNIQUE CONSTRAINT: (license_key, device_fingerprint)
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.5
 * @version 2026-06-12
 *
 * @property int         $id
 * @property int         $business_id
 * @property string      $license_key
 * @property string|null $device_fingerprint
 * @property string      $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $last_synced_at
 * @property int         $grace_period_days
 */
class DeviceActivation extends Model
{
    // ── Status Constants ──────────────────────────────────────────────────────
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_PENDING = 'pending';

    // ── Device Type Grace Periods (days) ──────────────────────────────────────
    protected const GRACE_DAYS_WEB    = 3;
    protected const GRACE_DAYS_MOBILE = 7;
    protected const GRACE_DAYS_POS    = 14; // POS devices get longest offline window

    protected $table = 'device_activations';

    protected $fillable = [
        'business_id',
        'license_key',
        'device_fingerprint',
        'status',
        'activated_at',
        'last_synced_at',
        'grace_period_days',
    ];

    protected $casts = [
        // NOTE: We do NOT cast to 'datetime' here because Eloquent's datetime
        // cast calls getConnection()->getQueryGrammar() to determine date format,
        // which requires a DB connection. This breaks pure unit tests.
        // Instead we use explicit Carbon accessor methods below.
        'activated_at'   => 'string',
        'last_synced_at' => 'string',
    ];

    // ── Carbon Accessors (no DB connection required) ──────────────────────────

    /**
     * Return last_synced_at as a Carbon instance.
     * Safe in unit tests: no DB connection required.
     */
    public function getLastSyncedAtAttribute(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Return activated_at as a Carbon instance.
     * Safe in unit tests: no DB connection required.
     */
    public function getActivatedAtAttribute(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function license()
    {
        return $this->belongsTo(License::class, 'license_key', 'license_key');
    }

    // ── Status Helpers ────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    // ── Grace Period Helpers ──────────────────────────────────────────────────

    /**
     * Calculate the absolute timestamp when the grace period expires.
     * Grace window starts from the last successful heartbeat (last_synced_at).
     */
    public function gracePeriodExpiresAt(): ?Carbon
    {
        if (!$this->last_synced_at) {
            return null;
        }
        return $this->last_synced_at->copy()->addDays($this->grace_period_days ?? self::GRACE_DAYS_WEB);
    }

    /**
     * How many full days remain in the grace period.
     * Returns 0 if grace has already expired.
     */
    public function gracePeriodDaysRemaining(): int
    {
        $expiresAt = $this->gracePeriodExpiresAt();
        if (!$expiresAt) {
            return 0;
        }
        $remaining = (int) now()->diffInDays($expiresAt, false);
        return max(0, $remaining);
    }

    /**
     * True if the device has not synced within its grace window.
     * This means the device is operating offline beyond the allowed window.
     */
    public function isGracePeriodExceeded(): bool
    {
        $expiresAt = $this->gracePeriodExpiresAt();
        return $expiresAt && now()->greaterThan($expiresAt);
    }

    // ── Static Factory Helpers ────────────────────────────────────────────────

    /**
     * Grace period days for a given device type.
     *
     * @param string $type  'web' | 'mobile' | 'pos'
     */
    public static function graceDaysForType(string $type): int
    {
        return match ($type) {
            'mobile' => self::GRACE_DAYS_MOBILE,
            'pos'    => self::GRACE_DAYS_POS,
            default  => self::GRACE_DAYS_WEB,
        };
    }
}
