<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Tenant\Models\Business;

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

        $business = Business::with('subscription.plan')->find($user->business_id);

        if (!$business || !$business->is_active) {
            return response()->json(['message' => 'Business is inactive or suspended'], 403);
        }

        if (!$business->subscription) {
            return response()->json([
                'message' => 'Payment Required. No active subscription.',
                'error_code' => 'SUBSCRIPTION_REQUIRED'
            ], 402);
        }

        if ($business->subscription->isPastDue()) {
            // Allow basic GET requests for settings/profile, block operational POST/PUT
            if (!$request->isMethod('get')) {
                return response()->json([
                    'message' => 'Payment Past Due. Please update billing to perform this action.',
                    'error_code' => 'SUBSCRIPTION_PAST_DUE'
                ], 402);
            }
        }
        
        if (!$business->subscription->isActive() && !$business->subscription->isPastDue()) {
            return response()->json([
                'message' => 'Subscription inactive or cancelled.',
                'error_code' => 'SUBSCRIPTION_INACTIVE'
            ], 402);
        }

        // Attach subscription limits to request for downstream controller validation
        $request->attributes->set('plan_limits', [
            'max_users' => $business->subscription->plan->max_users ?? 1,
            'max_locations' => $business->subscription->plan->max_locations ?? 1,
        ]);

        return $next($request);
    }
}
