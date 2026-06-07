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

class ImportProductsCsvJob implements ShouldQueue
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
            Log::error("ImportProductsCsvJob: File not found at {$this->filePath}");
            Cache::store('redis')->put("import_status_products_{$this->userId}", 'failed', 3600);
            return;
        }

        $file = fopen($this->filePath, 'r');
        $header = fgetcsv($file); // Read headers

        $batchSize = 100;
        $records = [];
        $now = now();

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 1 || empty($row[0])) continue; // Skip empty rows

            // Strict tenant isolation and defaults applied per row
            $records[] = [
                'business_id' => $this->businessId,
                'name' => $row[0] ?? 'Unnamed Product',
                'sku' => $row[1] ?: Str::random(8),
                'type' => strtolower($row[2] ?? 'single'),
                'barcode_type' => $row[3] ?: 'C128',
                'unit_id' => !empty($row[4]) ? (int)$row[4] : null,
                'brand_id' => !empty($row[5]) ? (int)$row[5] : null,
                'category_id' => !empty($row[6]) ? (int)$row[6] : null,
                'enable_stock' => strtolower($row[7] ?? 'yes') === 'yes' ? 1 : 0,
                'alert_quantity' => !empty($row[8]) ? (int)$row[8] : 0,
                'sell_price_inc_tax' => !empty($row[9]) ? (float)$row[9] : 0,
                'created_by' => $this->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($records) >= $batchSize) {
                DB::table('products')->insert($records);
                $records = [];
            }
        }

        if (count($records) > 0) {
            DB::table('products')->insert($records);
        }

        fclose($file);
        unlink($this->filePath); // Cleanup temp file

        Cache::store('redis')->put("import_status_products_{$this->userId}", 'completed', 3600);
        Log::info("Products CSV Import completed for business {$this->businessId}");
    }

    public function failed(\Throwable $exception)
    {
        Log::error("ImportProductsCsvJob Failed: " . $exception->getMessage());
        Cache::store('redis')->put("import_status_products_{$this->userId}", 'failed', 3600);
    }
}
