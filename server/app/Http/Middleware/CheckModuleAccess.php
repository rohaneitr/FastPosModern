<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module = null): Response
    {
        // Try to identify the module from the parameter, or the route name prefix, or the URL prefix
        if (!$module) {
            $routeName = $request->route() ? $request->route()->getName() : '';
            if ($routeName) {
                $parts = explode('.', $routeName);
                if (count($parts) > 0) {
                    $module = $parts[0];
                }
            } else {
                $path = $request->path();
                $parts = explode('/', $path);
                // Assume structure /api/v1/{module}/...
                if (count($parts) >= 3 && $parts[0] === 'api' && $parts[1] === 'v1') {
                    $module = $parts[2];
                }
            }
        }

        if (!$module) {
            return $next($request);
        }

        // Assume the user is authenticated and belongs to a business
        $user = $request->user();
        
        if (!$user) {
             return response()->json([
                'error' => 'Unauthorized.',
                'message' => 'User not authenticated.',
            ], 401);
        }

        // Get business from user
        $business = $user->business ?? null;
        
        if (!$business) {
            return response()->json([
                'error' => 'No Business Context.',
                'message' => 'The user is not associated with any business.',
            ], 403);
        }

        // Check active modules
        $activeModules = $business->active_modules ?? [];
        if (!is_array($activeModules)) {
            $activeModules = json_decode($activeModules, true) ?? [];
        }

        // 'core_pos' is generally always active or handled differently, but we check if the required module is in the array.
        if (!in_array(strtolower($module), array_map('strtolower', $activeModules))) {
            return response()->json([
                'error' => 'Module Access Denied',
                'message' => "Your subscription plan does not include access to the '{$module}' module. Please upgrade to use this feature."
            ], 403);
        }

        return $next($request);
    }
}
