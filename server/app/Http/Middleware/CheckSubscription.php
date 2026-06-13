<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Services\TenantContextCache;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // SuperAdmin bypasses subscription checks
        if ($user->hasRole('SuperAdmin')) {
            return $next($request);
        }

        if (!$user->business_id) {
            return response()->json(['message' => 'No business associated with user'], 403);
        }

        // Redis-cached Business+subscription+plan read.
        // Cache miss (~first request, or after Business::save() invalidation):
        //   → 3 SQL queries → result stored in Redis for TENANT_CACHE_TTL seconds.
        // Cache hit (~all subsequent requests within TTL):
        //   → sub-millisecond Redis read → zero SQL queries.
        $business = TenantContextCache::get($user->business_id);

        if (!$business || !$business->is_active) {
            return response()->json(['message' => 'Business is inactive or suspended'], 403);
        }

        if (!$business->subscription) {
            return response()->json([
                'message' => 'Payment Required. No active subscription.',
                'error_code' => 'SUBSCRIPTION_REQUIRED'
            ], 402);
        }

        $isExpired = false;

        if (!$business->isTrialActive() && !$business->isSubscriptionActive()) {
            $isExpired = true;
        }
        
        if ($isExpired || (!$business->subscription->isActive() && !$business->subscription->isPastDue())) {
            if (!$request->isMethod('get')) {
                return response()->json([
                    'message' => 'Your trial or subscription has expired. Please upgrade to continue using FastPosModern.',
                    'error_code' => 'SUBSCRIPTION_EXPIRED',
                    'redirect_url' => '/business/billing/expired'
                ], 402);
            }
        }

        // Attach subscription limits to request for downstream controller validation
        $request->attributes->set('plan_limits', [
            'max_users' => $business->subscription->plan->max_users ?? 1,
            'max_locations' => $business->subscription->plan->max_locations ?? 1,
        ]);

        return $next($request);
    }
}
