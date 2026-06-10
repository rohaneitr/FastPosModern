<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index()
    {
        $disk = Storage::disk('local');
        $files = $disk->files('backups');
        
        $backups = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'gz' || pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = [
                    'file_name' => basename($file),
                    'file_size' => round($disk->size($file) / 1048576, 2) . ' MB',
                    'last_modified' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                ];
            }
        }
        
        usort($backups, function($a, $b) {
            return strtotime($b['last_modified']) - strtotime($a['last_modified']);
        });

        return response()->json($backups);
    }

    public function run()
    {
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '5432');
        
        $fileName = 'backup_' . date('Y_m_d_H_i_s') . '.sql.gz';
        $path = storage_path('app/backups/' . $fileName);
        
        if (!File::exists(storage_path('app/backups'))) {
            File::makeDirectory(storage_path('app/backups'), 0755, true);
        }

        // Run pg_dump asynchronously
        $command = "PGPASSWORD='{$dbPass}' pg_dump -h {$dbHost} -p {$dbPort} -U {$dbUser} -d {$dbName} | gzip > {$path}";
        exec($command . ' > /dev/null 2>&1 &');

        return response()->json(['message' => 'Backup queued successfully.']);
    }

    public function download(Request $request)
    {
        $fileName = $request->input('file_name');
        if (!$fileName) {
            $fileName = $request->query('file_name');
        }

        $filePath = storage_path('app/backups/' . basename($fileName));

        if (!File::exists($filePath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Memory-safe chunked streaming for massive files (5GB+)
        $response = new StreamedResponse(function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                throw new \Exception("Could not open file for streaming.");
            }

            while (!feof($handle)) {
                // Read and output in 1MB chunks to prevent memory exhaustion
                echo fread($handle, 1048576);
                flush();
                if (ob_get_level() > 0) {
                    ob_flush();
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($fileName) . '"');
        $response->headers->set('Content-Length', filesize($filePath));
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    public function upload(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:sql,gz|max:5000000' // 5GB limit (configurable)
        ]);

        $file = $request->file('backup_file');
        
        if (!File::exists(storage_path('app/backups'))) {
            File::makeDirectory(storage_path('app/backups'), 0755, true);
        }
        
        $file->storeAs('backups', $file->getClientOriginalName(), 'local');

        return response()->json(['message' => 'Backup uploaded successfully']);
    }

    public function restore(Request $request)
    {
        $fileName = $request->input('file_name');
        $filePath = storage_path('app/backups/' . basename($fileName));

        if (!File::exists($filePath)) {
            return response()->json(['message' => 'Backup file not found'], 404);
        }

        // Delegate to a queued background job since restoring a large DB will hit PHP-FPM execution limits.
        \App\Modules\SuperAdmin\Jobs\RestoreDatabaseJob::dispatch($filePath);

        return response()->json(['message' => 'Database restore queued successfully. The system will go into maintenance mode momentarily during the restoration.']);
    }
}
