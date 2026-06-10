<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // ALWAYS ALLOW SuperAdmin endpoints to pass through so the Dashboard functions
        if ($request->is('api/v1/superadmin/*') || $request->is('api/v1/superadmin')) {
            return $next($request);
        }

        // Block everything else if cache flag is set
        if (Cache::has('maintenance_mode_enabled')) {
            $message = Cache::get('maintenance_message', 'System is currently undergoing scheduled maintenance.');
            return response()->json([
                'error' => 'Maintenance Mode',
                'message' => $message,
                'directive' => 'MAINTENANCE_LOCK'
            ], 503);
        }

        return $next($request);
    }
}
