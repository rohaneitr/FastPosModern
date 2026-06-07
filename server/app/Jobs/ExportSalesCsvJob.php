<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ExportSalesCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $businessId;
    public $startDate;
    public $endDate;
    public $userId;

    public function __construct($businessId, $startDate, $endDate, $userId)
    {
        $this->businessId = $businessId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
    }

    public function handle()
    {
        $sales = DB::table('transactions')
            ->where('business_id', $this->businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->select('invoice_no', 'transaction_date', 'total_before_tax', 'tax_amount', 'final_total')
            ->orderBy('transaction_date', 'desc')
            ->get();

        $filename = "exports/sales_export_{$this->businessId}_" . time() . ".csv";
        
        $csvData = fopen('php://temp', 'r+');
        fputcsv($csvData, ['Invoice No', 'Date', 'Subtotal', 'Tax', 'Final Total']);

        foreach ($sales as $row) {
            fputcsv($csvData, [
                $row->invoice_no,
                $row->transaction_date,
                $row->total_before_tax,
                $row->tax_amount,
                $row->final_total
            ]);
        }
        rewind($csvData);
        Storage::disk('public')->put($filename, stream_get_contents($csvData));
        fclose($csvData);

        // Record the completed export filename into Cache, accessible to the user
        Cache::put("export_status_{$this->userId}", $filename, 3600);

        Log::info("Sales CSV Export completed as {$filename} for user {$this->userId}");
    }

    public function failed(\Throwable $exception)
    {
        Log::error("ExportSalesCsvJob Failed for Business {$this->businessId}: " . $exception->getMessage());
        Cache::put("export_status_{$this->userId}", 'failed', 3600);
    }
}
