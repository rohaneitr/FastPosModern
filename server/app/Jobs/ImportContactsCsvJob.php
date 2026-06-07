<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportContactsCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $businessId;
    public $userId;

    public function __construct($filePath, $businessId, $userId)
    {
        $this->filePath = $filePath;
        $this->businessId = $businessId;
        $this->userId = $userId;
    }

    public function handle()
    {
        if (!file_exists($this->filePath)) {
            Log::error("ImportContactsCsvJob: File not found at {$this->filePath}");
            Cache::store('redis')->put("import_status_contacts_{$this->userId}", 'failed', 3600);
            return;
        }

        $file = fopen($this->filePath, 'r');
        $header = fgetcsv($file);

        $batchSize = 100;
        $records = [];
        $now = now();

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 2 || empty($row[1])) continue; // Need at least a name

            $records[] = [
                'business_id' => $this->businessId,
                'type' => strtolower($row[0] ?? 'customer'),
                'name' => $row[1] ?? 'Unnamed Contact',
                'first_name' => $row[2] ?? null,
                'last_name' => $row[3] ?? null,
                'email' => $row[4] ?? null,
                'contact_id' => $row[5] ?: Str::random(8),
                'tax_number' => $row[6] ?? null,
                'mobile' => $row[7] ?? null,
                'created_by' => $this->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($records) >= $batchSize) {
                DB::table('contacts')->insert($records);
                $records = [];
            }
        }

        if (count($records) > 0) {
            DB::table('contacts')->insert($records);
        }

        fclose($file);
        unlink($this->filePath);

        Cache::store('redis')->put("import_status_contacts_{$this->userId}", 'completed', 3600);
        Log::info("Contacts CSV Import completed for business {$this->businessId}");
    }

    public function failed(\Throwable $exception)
    {
        Log::error("ImportContactsCsvJob Failed: " . $exception->getMessage());
        Cache::store('redis')->put("import_status_contacts_{$this->userId}", 'failed', 3600);
    }
}
