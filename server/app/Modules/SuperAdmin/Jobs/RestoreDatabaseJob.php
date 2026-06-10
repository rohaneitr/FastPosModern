<?php

namespace App\Modules\SuperAdmin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RestoreDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours max for massive restore
    public $tries = 1;

    protected $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle()
    {
        try {
            Log::info("Starting DB Restore from " . $this->filePath);
            
            // Put application in maintenance mode with a bypass secret
            Artisan::call('down', ['--secret' => 'superadmin-bypass']);

            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPass = env('DB_PASSWORD');
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbPort = env('DB_PORT', '5432');

            // Force terminate active connections to prevent pg_restore locks
            $terminateCmd = "PGPASSWORD='{$dbPass}' psql -h {$dbHost} -p {$dbPort} -U {$dbUser} -c \"SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '{$dbName}' AND pid <> pg_backend_pid();\"";
            exec($terminateCmd);

            $isGzip = pathinfo($this->filePath, PATHINFO_EXTENSION) === 'gz';
            
            // Re-create the schema to ensure a clean slate
            $dropSchemaCmd = "PGPASSWORD='{$dbPass}' psql -h {$dbHost} -p {$dbPort} -U {$dbUser} -d {$dbName} -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;'";
            exec($dropSchemaCmd);

            if ($isGzip) {
                // zcat might not exist on all distros, gzip -d -c is safer.
                $restoreCmd = "gzip -d -c {$this->filePath} | PGPASSWORD='{$dbPass}' psql -h {$dbHost} -p {$dbPort} -U {$dbUser} -d {$dbName}";
                exec($restoreCmd);
            } else {
                $restoreCmd = "PGPASSWORD='{$dbPass}' psql -h {$dbHost} -p {$dbPort} -U {$dbUser} -d {$dbName} < {$this->filePath}";
                exec($restoreCmd);
            }

            Log::info("DB Restore completed successfully.");
        } catch (\Exception $e) {
            Log::error("DB Restore Failed: " . $e->getMessage());
        } finally {
            Artisan::call('up');
            Log::info("Application is back online.");
        }
    }
}
