<?php

namespace App\Modules\SuperAdmin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class RunSystemBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbPass = config('database.connections.pgsql.password');
        $dbHost = config('database.connections.pgsql.host', '127.0.0.1');
        $dbPort = config('database.connections.pgsql.port', '5432');
        
        $fileName = 'backup_' . date('Y_m_d_H_i_s') . '.sql.gz';
        $path = storage_path('app/backups/' . $fileName);
        
        if (!File::exists(storage_path('app/backups'))) {
            File::makeDirectory(storage_path('app/backups'), 0755, true);
        }

        // Use Symfony Process to securely pass passwords via ENV map instead of process arguments
        $process = \Symfony\Component\Process\Process::fromShellCommandline('pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" | gzip > "${BACKUP_PATH}"');
        $process->setTimeout(3600); // 1-hour timeout for massive databases
        $process->setEnv([
            'PGPASSWORD' => $dbPass,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_USER' => $dbUser,
            'DB_NAME' => $dbName,
            'BACKUP_PATH' => $path,
            'PATH' => getenv('PATH') // inherit path for pg_dump/gzip
        ]);
        
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
        }
    }
}
