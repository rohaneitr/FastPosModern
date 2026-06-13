<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * AuditLog / Activity Model — Phase 9: Enterprise Audit Trail
 *
 * Extends Spatie's Activity model with strict multi-tenant isolation.
 *
 * ── TENANT ISOLATION STRATEGY ──────────────────────────────────────────────
 *
 * Every activity log row carries a `business_id` foreign key. Isolation is
 * enforced at TWO layers:
 *
 *   1. WRITE-TIME: The `creating` hook auto-assigns business_id from the
 *      authenticated user. If no user is authenticated (CLI/job/webhook),
 *      the business_id remains null — SuperAdmin queries see these.
 *
 *   2. READ-TIME: The global scope filters all SELECT queries to the current
 *      user's business_id. The scope follows the same FAIL CLOSED pattern as
 *      BusinessScope:
 *        - Auth user with business_id → filter to that business (tenant path)
 *        - SuperAdmin → no filter (cross-tenant visibility intentional)
 *        - Unauthenticated / no business_id → whereRaw('1 = 0') (fail closed)
 *
 * This means a Cashier at Business A can NEVER read audit logs from Business B,
 * even if they somehow construct a direct query — the global scope prevents it.
 *
 * ── PII IN LOGS ────────────────────────────────────────────────────────────
 *
 * The Auditable trait (used on models) masks sensitive fields BEFORE Spatie
 * records the `properties` JSON. This model adds a second defence layer:
 * the `maskSensitiveProperties()` accessor strips any PII that slipped through
 * if the model is accessed directly.
 *
 * @property int|null    $business_id
 * @property string|null $log_name
 * @property string      $description
 * @property string|null $event
 * @property array|null  $properties
 */
class Activity extends SpatieActivity
{
    /**
     * Fields that are ALWAYS masked in stored audit properties.
     * Any field in this list is replaced with '********' at write-time
     * by the Auditable trait, and at read-time by the accessor below.
     */
    public const MASKED_FIELDS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'stripe_id',
        'stripe_customer_id',
        'card_last_four',
        'card_brand',
        'pm_type',
        'pm_last_four',
        'api_secret',
    ];

    protected $fillable = [
        'business_id',
        'log_name',
        'description',
        'subject_type',
        'event',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
    ];

    protected $casts = [
        'properties' => 'collection',
    ];

    /**
     * Boot the model: assign business_id on create and apply tenant scope.
     */
    protected static function booted(): void
    {
        parent::booted();

        // ── WRITE: Auto-assign business_id from authenticated user ────────────
        static::creating(function (self $activity) {
            if (empty($activity->business_id) && auth()->hasUser() && auth()->user()->business_id) {
                $activity->business_id = auth()->user()->business_id;
            }
        });

        // ── READ: Tenant isolation global scope ───────────────────────────────
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            if (auth()->hasUser()) {
                $user = auth()->user();

                // SuperAdmin: full cross-tenant visibility (no scope applied)
                if ($user->hasRole('SuperAdmin')) {
                    return;
                }

                // Tenant user: strictly scoped to their business
                if ($user->business_id) {
                    $builder->where('activity_log.business_id', $user->business_id);
                    return;
                }
            }

            // FAIL CLOSED: no authenticated user or no business_id
            // Returns zero rows — never leaks data by default.
            $builder->whereRaw('1 = 0');
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // ── PII Sanitisation (Read-time Defence) ──────────────────────────────────

    /**
     * Accessor: Return the properties collection with masked PII fields.
     *
     * This is the SECOND line of defence. The Auditable trait masks PII before
     * write. This accessor ensures that even rows written without masking
     * (e.g. from legacy code) return safe data when read.
     *
     * Spatie's getPropertiesAttribute() returns a Collection — we override it.
     */
    public function getPropertiesAttribute($value): \Illuminate\Support\Collection
    {
        $properties = parent::getPropertiesAttribute($value);

        if (! $properties instanceof \Illuminate\Support\Collection) {
            return collect();
        }

        // Mask inside properties->attributes and properties->old
        foreach (['attributes', 'old'] as $section) {
            if ($properties->has($section)) {
                $sectionData = $properties->get($section);
                if (is_array($sectionData)) {
                    foreach (self::MASKED_FIELDS as $field) {
                        if (array_key_exists($field, $sectionData)) {
                            $sectionData[$field] = '********';
                        }
                    }
                    $properties->put($section, $sectionData);
                }
            }
        }

        return $properties;
    }
}
