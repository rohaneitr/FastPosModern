<?php

namespace App\Domain\Imports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use App\Domain\Imports\Models\ImportStatus;
use Illuminate\Bus\Batch;
use Throwable;

class ImportFileMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $businessId;
    protected int $importStatusId;
    protected string $filePath;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600; // 10 minutes for massive files

    public function __construct(int $businessId, int $importStatusId, string $filePath)
    {
        $this->businessId = $businessId;
        $this->importStatusId = $importStatusId;
        $this->filePath = $filePath;
    }

    public function handle(): void
    {
        $importStatus = ImportStatus::find($this->importStatusId);
        if (!$importStatus) {
            Storage::disk('local')->delete($this->filePath);
            return;
        }

        $absolutePath = Storage::disk('local')->path($this->filePath);

        $handle = @fopen($absolutePath, 'r');
        if (!$handle) {
            $this->failAndCleanup($importStatus, "Failed to open streamed file. Possibly missing or unreadable.");
            return;
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $this->failAndCleanup($importStatus, "CSV file is empty or malformed.");
            return;
        }

        // Clean headers (trim whitespace, lowercase)
        $headers = array_map(function($header) {
            return strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header)));
        }, $headers);

        // Initialize empty batch dynamically to prevent memory accumulation of Jobs
        $businessId = $this->businessId;
        $importStatusId = $this->importStatusId;
        
        $batch = Bus::batch([])
            ->finally(function (Batch $batch) use ($businessId, $importStatusId) {
                // Determine true final status upon completion
                $statusRecord = ImportStatus::find($importStatusId);
                if ($statusRecord) {
                    $finalStatus = $statusRecord->failed_rows > 0 ? 'partial_success' : 'completed';
                    $statusRecord->update(['status' => $finalStatus]);
                    
                    // Dispatch Real-Time WebSockets Event
                    event(new \App\Events\ImportCompletedEvent($businessId, $importStatusId, $finalStatus));
                }
            })
            ->dispatch();

        $chunkSize = 100;
        $chunk = [];
        $rowIndex = 2; // Row 1 is header

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows perfectly
            if (empty(array_filter($row))) {
                $rowIndex++;
                continue;
            }

            // Map associative array safely
            $mapped = [];
            foreach ($headers as $i => $headerName) {
                $mapped[$headerName] = $row[$i] ?? null;
            }

            $chunk[] = $mapped;

            if (count($chunk) === $chunkSize) {
                // Directly inject chunk to active batch and free memory instantly
                $batch->add(new ProcessProductImportChunk($this->businessId, $this->importStatusId, $chunk, $rowIndex - $chunkSize + 1));
                $chunk = [];
            }
            $rowIndex++;
        }

        // Catch the remaining lines
        if (!empty($chunk)) {
            $batch->add(new ProcessProductImportChunk($this->businessId, $this->importStatusId, $chunk, $rowIndex - count($chunk)));
        }
        
        fclose($handle);

        $totalRowsToProcess = $rowIndex - 2;
        $importStatus->update([
            'total_rows' => $totalRowsToProcess,
            'status' => 'processing'
        ]);

        if ($totalRowsToProcess === 0) {
            $batch->cancel();
            $this->failAndCleanup($importStatus, "The CSV contains no data rows.");
            return;
        }

        // Hostage File Cleared immediately!
        // We do NOT wait for the queue to finish. The payloads are securely in Redis/DB.
        Storage::disk('local')->delete($this->filePath);
    }

    protected function failAndCleanup(ImportStatus $importStatus, string $errorMessage)
    {
        $importStatus->update([
            'status' => 'failed',
            'errors' => ['file' => $errorMessage]
        ]);
        Storage::disk('local')->delete($this->filePath);
    }
}
