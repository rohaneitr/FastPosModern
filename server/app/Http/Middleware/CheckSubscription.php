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

        $business = Business::find($user->business_id);

        if (!$business || !$business->is_active) {
            return response()->json(['message' => 'Business is inactive or suspended'], 403);
        }

        $isExpired = false;

        if ($business->subscription_status === 'pending_activation') {
            return response()->json([
                'message' => 'Your account is pending license activation. Please enter your license code.',
                'error_code' => 'LICENSE_REQUIRED',
                'redirect_url' => '/activation'
            ], 402); // Use 402 Payment Required or 403
        }

        // Check legacy subscriptions if needed, or rely on businesses table
        if ($business->subscription_status === 'Expired' || $business->subscription_status === 'Suspended') {
            $isExpired = true;
        }

        if ($business->subscription_ends_at) {
            $endsAt = \Carbon\Carbon::parse($business->subscription_ends_at);
            if ($endsAt->isPast()) {
                $isExpired = true;
            }
        }

        if ($isExpired) {
            return response()->json([
                'message' => 'Your subscription has expired. Please contact Fast Computer & Technology Support to renew your license.',
                'error_code' => 'SUBSCRIPTION_EXPIRED',
                'redirect_url' => '/business/billing/expired'
            ], 402);
        }

        // Attach subscription limits to request for downstream controller validation
        $request->attributes->set('plan_limits', [
            'max_users' => 9999, // default unbounded for now
            'max_locations' => 9999,
        ]);

        return $next($request);
    }
}
