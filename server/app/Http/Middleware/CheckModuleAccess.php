<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Tenant\Models\Business;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $module
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->hasRole('SuperAdmin')) {
            return $next($request);
        }

        if (!$user->business_id) {
            return response()->json(['message' => 'No business associated with user'], 403);
        }

        $business = Business::with('subscription.plan')->find($user->business_id);
        if (!$business || !$business->subscription || !$business->subscription->plan) {
            return response()->json(['message' => 'No active subscription or plan found.'], 403);
        }

        $features = $business->subscription->plan->features ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        if (!in_array($module, $features)) {
            return response()->json([
                'error' => 'Module Locked',
                'message' => "Your current plan does not include the {$module} module. Please upgrade your plan."
            ], 403);
        }

        return $next($request);
    }
}
