<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC Shadow Logger — Phase A: Non-Blocking Instrumentation
 *
 * This middleware observes every permission check outcome and logs it to
 * rbac_shadow_log WITHOUT blocking or modifying any request behavior.
 * It is purely diagnostic and can be safely removed once Strict Mode is verified.
 */
class RbacShadowLogger
{
    public function handle(Request $request, Closure $next, string $permission = '')
    {
        $response = $next($request);

        // Silently attempt to log — never crash the request
        try {
            if ($permission && Schema::hasTable('rbac_shadow_log')) {
                $user = $request->user();
                $allowed = $user ? $user->can($permission) : false;

                DB::table('rbac_shadow_log')->insert([
                    'user_id'              => $user?->id,
                    'route'                => $request->route()?->uri() ?? $request->path(),
                    'permission_requested' => $permission,
                    'result'               => $allowed ? 'allow' : 'deny',
                    'source'               => 'middleware',
                    'ip_address'           => $request->ip(),
                    'user_agent'           => substr($request->userAgent() ?? '', 0, 500),
                    'logged_at'            => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Intentionally swallowed — shadow logger must never block
        }

        return $response;
    }
}
