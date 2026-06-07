<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Get all backups
     */
    public function index(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $files = $disk->files(config('backup.backup.name'));

        $backups = [];
        foreach ($files as $file) {
            if (substr($file, -4) === '.zip') {
                $backups[] = [
                    'file_path' => $file,
                    'file_name' => basename($file),
                    'file_size' => $this->formatBytes($disk->size($file)),
                    'last_modified' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($file))->toDateTimeString()
                ];
            }
        }

        // reverse sort by name (date)
        $backups = array_reverse($backups);

        return response()->json($backups);
    }

    /**
     * Run Manual Backup
     */
    public function runBackup(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        // Dispatch backup command asynchronously so we don't timeout the HTTP request
        // In Laravel 11, you can just call Artisan::queue('backup:run') if queue is configured
        // Otherwise we just run it sync and return. For now we use queue.
        Artisan::queue('backup:run', ['--only-db' => true]);

        return response()->json(['message' => 'Backup process has been queued. It will be available shortly.']);
    }

    /**
     * Download Backup
     */
    public function download(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { 
            abort(403, 'Unauthorized access.'); 
        }

        $request->validate([
            'file_name' => 'required|string'
        ]);

        $fileName = $request->file_name;
        $diskName = config('backup.backup.destination.disks')[0] ?? 'local';
        $disk = Storage::disk($diskName);
        $file = config('backup.backup.name') . '/' . $fileName;

        if ($disk->exists($file)) {
            return $disk->download($file);
        }

        return response()->json(['message' => 'Backup file not found'], 404);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
