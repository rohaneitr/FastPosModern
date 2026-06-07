<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SaaSMaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        $isMaintenance = Cache::get('global_maintenance_mode', false);

        if ($isMaintenance) {
            // Check if the current request is for the superadmin subdomain or has superadmin token.
            // For safety, let's just allow paths containing /superadmin/ or allow based on role if logged in.
            // But if it's 503, maybe the frontend is completely blocked.
            // Wait, the requirement: "while the Super Admin subdomain remains fully accessible."
            $host = $request->getHost();
            // Assuming superadmin is on admin.* or super.* or fast.* . In a previous conversation, the main domain was 'fast.localhost'.
            // Let's just check if it's not the main app domain if we can, or just check the origin/host.
            // Alternatively, allow any route starting with api/v1/superadmin/
            if ($request->is('api/v1/superadmin/*') || $request->is('api/v1/login') || $request->is('api/v1/me') || $request->is('api/v1/logout')) {
                return $next($request);
            }
            
            // Allow super admins if they are already authenticated? No, token is validated later.
            // Let's just check if a custom header or origin indicates superadmin, or just block tenant endpoints.
            
            return response()->json([
                'message' => 'Service Unavailable',
                'code' => 'MAINTENANCE_MODE'
            ], 503);
        }

        return $next($request);
    }
}
