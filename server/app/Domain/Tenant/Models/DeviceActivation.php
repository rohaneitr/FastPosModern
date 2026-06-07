<?php

namespace App\Domain\Tenant\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeviceActivation
 *
 * Tracks a single physical device bound to a tenant's license.
 *
 * Status lifecycle:
 *   active   → device is online / within grace period
 *   suspended → grace period exceeded; access denied until next heartbeat
 *   revoked   → manually revoked by Super Admin; cannot self-recover
 *
 * Grace periods (configurable per-row via grace_period_days):
 *   hybrid  → up to 30 days offline
 *   mobile  → up to  7 days offline
 *   web     → N/A   (always requires live session)
 */
class DeviceActivation extends Model
{
    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_ACTIVE    = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_REVOKED   = 'revoked';

    // ── Default grace periods (days) by plan type ─────────────────────────────

    const GRACE_HYBRID = 30;
    const GRACE_MOBILE = 7;
    const GRACE_WEB    = 0;   // web plans never go offline — no grace needed

    // ─────────────────────────────────────────────────────────────────────────

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
        'activated_at'    => 'datetime',
        'last_synced_at'  => 'datetime',
        'grace_period_days' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    // ── Grace period helpers ──────────────────────────────────────────────────

    /**
     * Returns the timestamp at which offline suspension kicks in.
     * NULL when grace_period_days is 0 (web plans — no offline grace).
     */
    public function gracePeriodExpiresAt(): ?Carbon
    {
        if (!$this->last_synced_at || $this->grace_period_days <= 0) {
            return null;
        }

        return $this->last_synced_at->copy()->addDays($this->grace_period_days);
    }

    /**
     * True when the device has been silent longer than its allowed grace period.
     */
    public function isGracePeriodExceeded(): bool
    {
        $expiresAt = $this->gracePeriodExpiresAt();

        if ($expiresAt === null) {
            // grace_period_days = 0 → must always be online; treat missing
            // last_synced_at as already exceeded.
            return $this->last_synced_at === null;
        }

        return $expiresAt->isPast();
    }

    /**
     * How many days remain in the offline grace period (negative = overdue).
     */
    public function gracePeriodDaysRemaining(): ?float
    {
        $expiresAt = $this->gracePeriodExpiresAt();
        if ($expiresAt === null) return null;

        return round(now()->floatDiffInDays($expiresAt, false), 1);
    }

    // ── Factory helper ────────────────────────────────────────────────────────

    /**
     * Returns the canonical grace_period_days for a given plan type string.
     */
    public static function graceDaysForType(string $type): int
    {
        return match ($type) {
            'hybrid' => self::GRACE_HYBRID,
            'mobile' => self::GRACE_MOBILE,
            default  => self::GRACE_WEB,
        };
    }
}
