<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            // Check DB
            DB::connection()->getPdo();
            
            // Check Redis
            Redis::connection()->ping();
            
            return response()->json([
                'status' => 'OK',
                'database' => 'connected',
                'redis' => 'connected',
                'timestamp' => now()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 503);
        }
    }
}
