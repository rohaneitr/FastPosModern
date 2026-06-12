<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EntitlementMiddleware
{
    /**
     * Handle an incoming request and check module entitlement.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->business_id) {
            return response()->json(['message' => '403 Forbidden: Unauthenticated or missing tenant context'], 403);
        }

        // SuperAdmins bypass this check
        if ($user->hasRole('SuperAdmin')) {
            return $next($request);
        }

        // Fetch the denormalized active_modules from the business table (fastest read)
        // Optionally, we could check the user's Spatie permissions: `$user->hasPermissionTo("module.{$moduleSlug}")`
        $business = \App\Modules\Tenant\Models\Business::find($user->business_id);
        
        if (!$business) {
            return response()->json(['message' => '403 Forbidden: Invalid Tenant'], 403);
        }

        $activeModules = $business->active_modules ?? [];

        if (!is_array($activeModules)) {
            $activeModules = json_decode($activeModules, true) ?? [];
        }

        if (!in_array($moduleSlug, $activeModules)) {
            return response()->json([
                'error' => 'module_not_subscribed',
                'message' => "403 Forbidden: The '{$moduleSlug}' module is not active for your subscription. Contact support to upgrade."
            ], 403);
        }

        return $next($request);
    }
}
