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

        // Auto-Disable Heartbeat Guard
        if ($request->hasHeader('X-Hardware-Hash') && $user && $user->business_id) {
            $hwHash = $request->header('X-Hardware-Hash');
            $device = \App\Modules\Tenant\Models\DeviceActivation::where('device_fingerprint', $hwHash)
                ->where('business_id', $user->business_id)
                ->first();

            if ($device && $device->status === 'revoked') {
                return response()->json([
                    'message' => 'This device has been revoked and is no longer authorized.',
                    'code' => 'DEVICE_REVOKED'
                ], 403);
            }
        }

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
                        'api/v1/superadmin*',
                        'api/v1/login',
                        'api/v1/password*'
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

        // Feature/Module Gating Check
        if ($user && $user->business && $user->business->status === 'active') {
            if ($request->is('api/v1/pharmacy/*') || $request->is('api/v1/pharmacy')) {
                $activeModules = is_string($user->business->active_modules) 
                                 ? json_decode($user->business->active_modules, true) 
                                 : ($user->business->active_modules ?? []);
                
                if (!is_array($activeModules) || !in_array('pharmacy', $activeModules)) {
                    return response()->json([
                        'message' => 'Module Access Denied. Your subscription plan does not include the Pharmacy module.',
                        'code' => 'MODULE_NOT_ENABLED'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
