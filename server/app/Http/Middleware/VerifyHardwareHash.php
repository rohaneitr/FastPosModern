<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifyHardwareHash
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Manager or Admins can bypass hardware lock
        if ($request->user() && $request->user()->hasRole(['Manager', 'BusinessAdmin', 'SuperAdmin'])) {
            return $next($request);
        }

        $deviceHash = $request->header('X-Device-Hash');
        
        if (!$deviceHash) {
            return response()->json(['message' => 'Hardware Lock: Missing X-Device-Hash header. Unauthorized terminal.'], 401);
        }

        // Cashiers MUST use a registered terminal for their location
        $user = $request->user();
        
        // Find if this device hash is registered as a terminal in the user's location
        $isValidTerminal = DB::table('terminals')
            ->where('business_id', $user->business_id)
            ->where('location_id', $user->location_id)
            ->where('device_hash', $deviceHash)
            ->where('is_active', true)
            ->exists();

        // Fallback: Check if they have an active cash register with this hash
        if (!$isValidTerminal) {
            $hasActiveRegister = DB::table('cash_registers')
                ->where('business_id', $user->business_id)
                ->where('opened_by_user_id', $user->id)
                ->where('device_hash', $deviceHash)
                ->whereIn('status', ['open', 'suspending'])
                ->exists();

            if (!$hasActiveRegister) {
                return response()->json(['message' => 'Hardware Lock: Terminal not recognized or authorized for your location.'], 401);
            }
        }

        return $next($request);
    }
}
