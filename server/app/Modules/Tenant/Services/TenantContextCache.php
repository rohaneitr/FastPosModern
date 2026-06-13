<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Business;
use Illuminate\Support\Facades\Cache;

/**
 * TenantContextCache — Phase 9: SRE Hardening
 *
 * Provides a Redis-backed cache for the Business model and its eagerly-loaded
 * subscription + plan relations. This is the single authoritative source for
 * tenant context throughout the request lifecycle.
 *
 * ── WHY THIS EXISTS ────────────────────────────────────────────────────────
 *
 * The `subscribed` middleware (CheckSubscription) fires on every authenticated
 * tenant request. It previously executed:
 *
 *   Business::with('subscription.plan')->find($user->business_id)
 *
 * That is 3 SQL queries per request (businesses + subscriptions + plans).
 * In a multi-tenant SaaS with 100 concurrent sessions and a 60-req/min API
 * limit, this generates ~300 redundant PostgreSQL round-trips per minute
 * reading rows that change at most once per day (plan upgrades, webhooks).
 *
 * With Redis caching, the FIRST request for a business warms the cache.
 * All subsequent requests for that business within the TTL window hit Redis
 * (sub-millisecond) instead of PostgreSQL (5-30ms). At 100 concurrent tenants
 * this eliminates ~29,700 PostgreSQL queries per 5-minute window.
 *
 * ── CACHE KEY FORMAT ───────────────────────────────────────────────────────
 *
 *   tenant.context.{business_id}
 *
 *   e.g. tenant.context.42
 *
 * ── TTL ────────────────────────────────────────────────────────────────────
 *
 *   Default: 5 minutes (300 seconds). Configurable via TENANT_CACHE_TTL env.
 *
 *   Rationale: Short enough that subscription changes (via Stripe webhook)
 *   propagate quickly; long enough to absorb burst traffic without hammering
 *   PostgreSQL.
 *
 * ── INVALIDATION ───────────────────────────────────────────────────────────
 *
 *   MANDATORY. Cache::forget() is called by the Business model's `saved` and
 *   `deleted` events (see Business::booted()). This guarantees:
 *
 *   - Stripe webhook activates subscription → Business::save() → cache cleared
 *   - Trial suspension cron suspends business → Business::save() → cache cleared
 *   - Business admin updates settings → Business::save() → cache cleared
 *   - Business soft-deleted → Business::delete() → cache cleared
 *
 *   The "forgotten" cache entry is rebuilt on the NEXT request — the one
 *   request that misses cache re-executes the 3 SQL queries, after which all
 *   subsequent requests hit Redis again. Maximum staleness = 0 seconds after
 *   any Business write.
 *
 * ── WHAT IS CACHED ─────────────────────────────────────────────────────────
 *
 *   The full Business Eloquent model with relations:
 *     - business.subscription (Subscription model)
 *     - business.subscription.plan (Plan model)
 *
 *   The cached object is a serialized Eloquent model. All model methods
 *   (isTrialActive(), isSubscriptionActive(), hasModule()) work on the
 *   deserialized object without any DB queries.
 *
 * @version Phase 9 — SRE Hardening
 */
class TenantContextCache
{
    /**
     * Cache TTL in seconds. Overridable via TENANT_CACHE_TTL env variable.
     */
    private const DEFAULT_TTL = 300;

    /**
     * Retrieve a Business with subscription + plan from cache or database.
     *
     * If the cache entry exists, returns the deserialized Business model instantly.
     * If not (cold start or after invalidation), queries PostgreSQL, caches the
     * result, and returns it.
     *
     * @param  int  $businessId
     * @return Business|null
     */
    public static function get(int $businessId): ?Business
    {
        $ttl = (int) env('TENANT_CACHE_TTL', self::DEFAULT_TTL);

        return Cache::remember(
            self::key($businessId),
            $ttl,
            fn () => Business::with('subscription.plan')->find($businessId)
        );
    }

    /**
     * Remove a specific tenant's context from the cache.
     *
     * Called by Business model events (saved, deleted) to guarantee
     * the cache is never stale after a write.
     *
     * @param  int  $businessId
     */
    public static function forget(int $businessId): void
    {
        Cache::forget(self::key($businessId));
    }

    /**
     * Returns the canonical Redis cache key for a given business ID.
     *
     * @param  int  $businessId
     */
    public static function key(int $businessId): string
    {
        return "tenant.context.{$businessId}";
    }

    /**
     * Warm the cache for a given business ID proactively.
     *
     * Useful after CreateNewTenantAction provisions a new business —
     * pre-warming avoids a cache miss on the tenant's very first request.
     *
     * @param  int  $businessId
     * @return Business|null
     */
    public static function warm(int $businessId): ?Business
    {
        self::forget($businessId); // Clear any stale entry first
        return self::get($businessId);
    }
}
