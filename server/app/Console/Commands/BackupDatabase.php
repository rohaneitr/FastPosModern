<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a safe SQL dump of the database and compress it into a secure storage directory.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $connection = env('DB_CONNECTION');

        $date = Carbon::now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('app/backups');
        
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $safeDatabaseName = basename($database);
        $filename = "backup_{$safeDatabaseName}_{$date}.sql";
        $filePath = "{$backupDir}/{$filename}";

        try {
            if ($connection === 'mysql') {
                $command = "mysqldump --user={$username} --password={$password} --host={$host} --port={$port} {$database} > {$filePath}";
                exec($command, $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new Exception("mysqldump failed with code {$returnVar}");
                }
            } elseif ($connection === 'sqlite') {
                // Determine absolute path for SQLite
                $dbPath = file_exists($database) ? $database : database_path(str_replace('database/', '', $database));
                if (!file_exists($dbPath)) {
                    // Try another common location if relative
                    $dbPath = database_path($database);
                }
                
                if (file_exists($dbPath)) {
                    copy($dbPath, $filePath);
                } else {
                    throw new Exception("SQLite database file not found at: {$database} or {$dbPath}");
                }
            } else {
                $this->error("Unsupported database connection: {$connection}");
                return Command::FAILURE;
            }

            // Compress the backup
            $gzPath = "{$filePath}.gz";
            exec("gzip -f {$filePath}");
            
            if (file_exists($gzPath)) {
                $this->info("Backup successfully created at: {$gzPath}");
                Log::info("Database backup created successfully: {$gzPath}");
            } else {
                throw new Exception("Failed to compress backup with gzip.");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Database backup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
