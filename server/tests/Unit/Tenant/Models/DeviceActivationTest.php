<?php

namespace Tests\Unit\Tenant\Models;

use App\Modules\Tenant\Models\DeviceActivation;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * DeviceActivationTest
 *
 * Pure unit tests — no database required.
 * Tests the grace period computation logic that controls whether
 * an offline POS device is allowed to continue operating.
 *
 * WHAT WE ARE PROVING:
 *   1. Grace period expiry timestamp is calculated from last_synced_at
 *   2. Days remaining is correctly floored (never negative)
 *   3. isGracePeriodExceeded() returns true only after window lapses
 *   4. isRevoked() and isActive() correctly reflect status constants
 *   5. graceDaysForType() returns correct per-type windows
 *   6. Null last_synced_at returns null for expiry (safe)
 *
 * NOTE ON ELOQUENT IN UNIT TESTS:
 *   DeviceActivation uses $casts = ['last_synced_at' => 'datetime'].
 *   Eloquent's cast system calls connection() to determine the date format.
 *   To avoid needing a DB, we inject Carbon objects via setRawAttributes()
 *   and then manually call the Carbon cast. Instead, we create a simple
 *   testable subclass that bypasses the Eloquent boot/cast cycle.
 *
 * @covers \App\Modules\Tenant\Models\DeviceActivation
 */
class DeviceActivationTest extends TestCase
{
    /**
     * Create a DeviceActivation instance that bypasses Eloquent boot.
     * We override last_synced_at directly as a Carbon object to avoid
     * the DB connection requirement from datetime casting.
     */
    private function makeDevice(array $attributes = []): DeviceActivation
    {
        $device = new DeviceActivation();

        // Bypass Eloquent casting by directly setting the attribute
        // as a Carbon instance on the underlying attributes array.
        // This matches how Eloquent itself stores cast values internally.
        foreach ($attributes as $key => $value) {
            if ($value instanceof Carbon) {
                // Force the raw attribute AND set the cached cast value
                $device->setRawAttributes(array_merge(
                    $device->getRawOriginal() ?? [],
                    [$key => $value->toDateTimeString()]
                ));
                // Access the property to prime the cast cache
                // We'll store it directly in a way that bypasses DB
            } else {
                $device->$key = $value;
            }
        }

        return $device;
    }

    /**
     * Inject a Carbon value for a datetime-cast attribute WITHOUT
     * triggering Eloquent's DB-dependent cast resolution.
     */
    private function setDateAttribute(DeviceActivation $device, string $attr, ?Carbon $value): void
    {
        // Use reflection to directly set the castable property
        // on the internal 'classCastCache' or just override via setAttribute
        // with string, then manually override the cast result in tests.
        $ref  = new \ReflectionClass($device);
        $prop = $ref->getProperty('attributes');
        $prop->setAccessible(true);
        $attrs = $prop->getValue($device);
        $attrs[$attr] = $value?->toDateTimeString();
        $prop->setValue($device, $attrs);

        // Clear the cast cache so it re-reads from attributes
        $cacheProp = $ref->getProperty('classCastCache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($device, []);
    }

    // ── 1. Status helpers ─────────────────────────────────────────────────────

    /** @test */
    public function is_active_returns_true_for_active_status(): void
    {
        $device = new DeviceActivation();
        $device->status = DeviceActivation::STATUS_ACTIVE;

        $this->assertTrue($device->isActive());
        $this->assertFalse($device->isRevoked());
    }

    /** @test */
    public function is_revoked_returns_true_for_revoked_status(): void
    {
        $device = new DeviceActivation();
        $device->status = DeviceActivation::STATUS_REVOKED;

        $this->assertTrue($device->isRevoked());
        $this->assertFalse($device->isActive());
    }

    /** @test */
    public function status_constants_have_expected_values(): void
    {
        $this->assertEquals('active',  DeviceActivation::STATUS_ACTIVE);
        $this->assertEquals('revoked', DeviceActivation::STATUS_REVOKED);
        $this->assertEquals('pending', DeviceActivation::STATUS_PENDING);
    }

    // ── 2. Grace period expiry calculation ────────────────────────────────────

    /** @test */
    public function grace_period_expires_at_is_null_when_never_synced(): void
    {
        $device = new DeviceActivation();
        $device->grace_period_days = 3;

        $this->assertNull($device->gracePeriodExpiresAt());
    }

    /** @test */
    public function grace_period_expires_at_is_last_synced_plus_grace_days(): void
    {
        $device = new DeviceActivation();
        $device->last_synced_at   = '2026-06-10 12:00:00'; // Accessor converts to Carbon
        $device->grace_period_days = 3;

        $expires = $device->gracePeriodExpiresAt();
        $this->assertNotNull($expires);
        $this->assertEquals('2026-06-13 12:00:00', $expires->toDateTimeString());
    }

    /** @test */
    public function grace_period_expiry_accounts_for_different_grace_days(): void
    {
        $device = new DeviceActivation();
        $device->last_synced_at   = '2026-06-01 00:00:00';
        $device->grace_period_days = 14;

        $expires = $device->gracePeriodExpiresAt();
        $this->assertNotNull($expires);
        $this->assertEquals('2026-06-15 00:00:00', $expires->toDateTimeString());
    }

    // ── 3. Days remaining ─────────────────────────────────────────────────────

    /** @test */
    public function grace_period_days_remaining_returns_zero_when_no_sync(): void
    {
        $device = new DeviceActivation();
        $device->grace_period_days = 7;

        $this->assertEquals(0, $device->gracePeriodDaysRemaining());
    }

    /** @test */
    public function grace_period_days_remaining_returns_zero_when_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20'));

        $device = new DeviceActivation();
        $device->last_synced_at   = '2026-06-10';
        $device->grace_period_days = 3;

        $this->assertEquals(0, $device->gracePeriodDaysRemaining());

        Carbon::setTestNow(null);
    }

    /** @test */
    public function grace_period_days_remaining_is_never_negative(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31'));

        $device = new DeviceActivation();
        $device->last_synced_at   = '2026-01-01';
        $device->grace_period_days = 3;

        $remaining = $device->gracePeriodDaysRemaining();
        $this->assertGreaterThanOrEqual(0, $remaining);

        Carbon::setTestNow(null);
    }

    // ── 4. isGracePeriodExceeded() ────────────────────────────────────────────

    /** @test */
    public function grace_period_is_not_exceeded_when_synced_recently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-12 10:00:00'));

        $device = new DeviceActivation();
        $device->last_synced_at   = '2026-06-11 10:00:00';
        $device->grace_period_days = 3;

        $this->assertFalse($device->isGracePeriodExceeded());

        Carbon::setTestNow(null);
    }

    /** @test */
    public function grace_period_is_exceeded_after_window_lapses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20'));

        $device = new DeviceActivation();
        $device->last_synced_at   = '2026-06-10';
        $device->grace_period_days = 3;

        $this->assertTrue($device->isGracePeriodExceeded());

        Carbon::setTestNow(null);
    }

    /** @test */
    public function grace_period_is_not_exceeded_when_never_synced(): void
    {
        $device = new DeviceActivation();
        $device->grace_period_days = 3;

        $this->assertFalse($device->isGracePeriodExceeded());
    }

    // ── 5. graceDaysForType() factory ─────────────────────────────────────────

    /** @test */
    public function grace_days_for_web_type_is_3(): void
    {
        $this->assertEquals(3, DeviceActivation::graceDaysForType('web'));
    }

    /** @test */
    public function grace_days_for_mobile_type_is_7(): void
    {
        $this->assertEquals(7, DeviceActivation::graceDaysForType('mobile'));
    }

    /** @test */
    public function grace_days_for_pos_type_is_14(): void
    {
        $this->assertEquals(14, DeviceActivation::graceDaysForType('pos'));
    }

    /** @test */
    public function grace_days_for_unknown_type_defaults_to_web(): void
    {
        $this->assertEquals(3, DeviceActivation::graceDaysForType('unknown_type'));
        $this->assertEquals(3, DeviceActivation::graceDaysForType(''));
    }
}
