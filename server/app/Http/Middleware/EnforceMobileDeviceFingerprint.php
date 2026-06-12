<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMobileDeviceFingerprint
{
    public function handle(Request $request, Closure $next): Response
    {
        $fingerprint = $request->header('X-Device-Fingerprint');

        if (!$fingerprint) {
            return response()->json([
                'error' => 'FPM Security: X-Device-Fingerprint header is required.'
            ], 401);
        }

        $user = $request->user();
        if (!$user || !$user->currentAccessToken()) {
            return response()->json([
                'error' => 'FPM Security: Unauthorized.'
            ], 401);
        }

        // We bind the fingerprint inside the token name or abilities.
        // Assuming we bound it as 'mobile_device:'.$fingerprint in abilities
        // or stored the fingerprint string directly into the token's 'name' property.
        $token = $user->currentAccessToken();
        
        // We will assert the token name strictly equals the exact fingerprint
        if ($token->name !== $fingerprint) {
            // Hijack detected! Revoke the token immediately.
            $token->delete();

            return response()->json([
                'error' => 'FPM Security: Device signature mismatch. Session revoked.'
            ], 401);
        }

        return $next($request);
    }
}
