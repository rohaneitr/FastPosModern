<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HealthController extends Controller
{
    /**
     * Health check endpoint for uptime monitoring.
     * Returns status of database, cache, and disk.
     */
    public function __invoke()
    {
        $checks = [];
        $healthy = true;

        // Database
        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        // Cache
        try {
            Cache::put('health_check', true, 10);
            $checks['cache'] = Cache::get('health_check') ? 'ok' : 'error';
        } catch (\Exception $e) {
            $checks['cache'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        // Disk
        $diskFree = disk_free_space(storage_path());
        $checks['disk_free_mb'] = round($diskFree / 1024 / 1024, 1);
        if ($diskFree < 100 * 1024 * 1024) { // < 100MB
            $healthy = false;
        }

        $checks['timestamp'] = Carbon::now()->toIso8601String();
        $checks['environment'] = app()->environment();
        $checks['php_version'] = phpversion();
        $checks['laravel_version'] = app()->version();

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
