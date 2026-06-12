<?php

namespace App\Modules\Tenant\Services;

/**
 * TenantContext — Safe Tenant Scoping for Background Execution
 *
 * PROBLEM:
 *   BusinessScope applies WHERE business_id = X only when auth()->hasUser().
 *   Queued jobs, scheduled commands, and Artisan calls run without an
 *   authenticated user, so BusinessScope silently passes through — returning
 *   all tenant data without any isolation.
 *
 * SOLUTION:
 *   Jobs MUST pass business_id at dispatch time and call
 *   TenantContext::set($businessId) inside handle() before any query.
 *   BusinessScope checks this context FIRST before falling back to auth().
 *
 * USAGE IN JOBS:
 *   // In constructor:
 *   public function __construct(int $businessId) {
 *       $this->businessId = $businessId;
 *   }
 *
 *   // In handle():
 *   TenantContext::set($this->businessId);
 *   // ... all queries now scoped to this business
 *   TenantContext::clear(); // always clear when done
 *
 * HTTP REQUESTS:
 *   Zero impact. BusinessScope checks auth() first, which always wins
 *   in an authenticated HTTP context. TenantContext is only consulted
 *   when auth()->hasUser() is false.
 */
class TenantContext
{
    private static ?int $businessId = null;

    /**
     * Set the active tenant context for the current process.
     * Call this at the top of every queued job handle() method.
     */
    public static function set(int $businessId): void
    {
        static::$businessId = $businessId;
    }

    /**
     * Get the active tenant business ID.
     * Returns null if neither context nor auth user is set.
     */
    public static function get(): ?int
    {
        return static::$businessId;
    }

    /**
     * Clear the tenant context.
     * Always call in a finally{} block after job execution.
     */
    public static function clear(): void
    {
        static::$businessId = null;
    }

    /**
     * Check if a tenant context is currently active.
     */
    public static function isActive(): bool
    {
        return static::$businessId !== null;
    }

    /**
     * Execute a callable within a specific tenant context.
     * Automatically cleans up after completion or failure.
     *
     * Usage:
     *   TenantContext::for($this->businessId, function() {
     *       Product::all(); // scoped to businessId
     *   });
     */
    public static function for(int $businessId, callable $callback): mixed
    {
        $previous = static::$businessId;
        static::set($businessId);

        try {
            return $callback();
        } finally {
            static::$businessId = $previous;
        }
    }
}
