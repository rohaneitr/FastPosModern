<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnforceActiveSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Allow super admins to bypass tenant-level locks if needed (optional based on rules, assuming tenant logic)
        if (!$user || !$user->business_id) {
            return $next($request);
        }

        // Cache or query subscription state
        $subscription = DB::table('saas_subscriptions')
            ->where('business_id', $user->business_id)
            ->first();

        if (!$subscription || now()->greaterThan($subscription->valid_until)) {
            // Check if the current route is trying to access the billing portal to renew
            // If yes, allow. If not, block.
            if ($request->is('api/*/settings/subscription/*')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Subscription expired or inactive. Please renew to continue accessing the ERP.',
                'error_code' => 'PAYMENT_REQUIRED',
                'valid_until' => $subscription->valid_until ?? null
            ], 402);
        }

        return $next($request);
    }
}
