<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If the user is authenticated and belongs to a business
        if ($user && $user->business) {
            // We only care about blocking business API routes (or dashboard routes)
            // if their status is pending_activation
            if ($user->business->status !== 'active') {
                
                // For API requests
                if ($request->is('api/v1/*')) {
                    // Exclude specific safe endpoints they might need while pending
                    $safeRoutes = [
                        'api/v1/tenant/activate-license',
                        'api/v1/logout',
                        'api/v1/me',
                        'api/v1/devices/activate',
                        'api/v1/superadmin*'
                    ];

                    $isSafe = false;
                    foreach ($safeRoutes as $route) {
                        if ($request->is($route)) {
                            $isSafe = true;
                            break;
                        }
                    }

                    if (!$isSafe) {
                        // If they are superadmin, we shouldn't block them from managing
                        if (!$user->hasRole('SuperAdmin')) {
                            return response()->json([
                                'message' => 'Your account is pending activation. Please enter a valid license key or contact support.',
                                'status' => 'pending_activation'
                            ], 402); // 402 Payment Required or 403 Forbidden
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
