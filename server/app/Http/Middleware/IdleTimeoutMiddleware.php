<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdleTimeoutMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $token = $user->currentAccessToken();

            if ($token) {
                // If the token has a specific volatile expiration or simply apply idle timeout to all
                $timeoutMinutes = config('session.lifetime', 120); // Default idle timeout
                
                // Sanctum updates last_used_at on the token. We can check the difference between now and last_used_at
                // Note: Sanctum may update last_used_at *during* the request. 
                // To be safe, we can track last_activity in the token's `abilities` or use last_used_at if it represents the last request's time.
                // However, since we are doing this in middleware, last_used_at might already be updated.
                // Instead, we can rely on a cache or the user_devices table if needed, 
                // but checking last_used_at before Sanctum touches it is tricky if we run after auth middleware.
                
                // Let's check the device last_activity instead, or use a cache key for the token
                $cacheKey = 'token_last_activity_' . $token->id;
                $lastActivity = cache($cacheKey);

                if ($lastActivity && (time() - $lastActivity) > ($timeoutMinutes * 60)) {
                    $token->delete();
                    cache()->forget($cacheKey);
                    return response()->json(['message' => 'Session expired due to inactivity. Please log in again.'], 401);
                }

                // Update activity using a raw timestamp instead of a Carbon object
                cache()->put($cacheKey, time(), now()->addMinutes($timeoutMinutes + 5));
            }
        }

        return $next($request);
    }
}
