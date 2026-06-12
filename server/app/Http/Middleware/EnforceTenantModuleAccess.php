<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnforceTenantModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $moduleSlug
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $moduleSlug)
    {
        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $businessId = $user->business_id;

        // 1. Check Redis Cache Matrix (1 Day Expiry)
        $activeModules = Cache::remember("tenant_modules:{$businessId}", 86400, function () use ($businessId) {
            return DB::table('tenant_modules')
                ->join('modules', 'tenant_modules.module_id', '=', 'modules.id')
                ->where('tenant_modules.business_id', $businessId)
                ->where('tenant_modules.is_active', true)
                ->where(function($query) {
                    $query->whereNull('tenant_modules.expires_at')
                          ->orWhere('tenant_modules.expires_at', '>', now());
                })
                ->pluck('modules.slug')
                ->toArray();
        });

        // 2. Strict Matrix Validation
        if (!in_array($moduleSlug, $activeModules)) {
            return response()->json([
                'message' => 'FPM Security: Module entitlement restricted. Upgrade your tier.',
                'error_code' => 'MODULE_RESTRICTED'
            ], 403);
        }

        return $next($request);
    }
}
