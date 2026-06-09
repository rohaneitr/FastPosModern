<?php

namespace App\Modules\Pharmacy\Listeners;

use App\Modules\Shared\Events\TransactionProcessing;
use App\Modules\Pharmacy\Exceptions\ExpiredStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnforceFEFOStockDeduction
{
    public function handle(TransactionProcessing $event)
    {
        $payload = $event->getPayload();
        $businessId = $payload['business_id'];

        foreach ($payload['lines'] as $line) {
            // Check if product is marked as medicine (Assuming the payload passes 'is_medicine' or we check category)
            // For testing & modularity, we assume the core injects 'product_id' and 'quantity'.
            
            // Check if this product has batches in pharmacy
            $hasBatches = DB::table('pharmacy_batches')
                ->where('business_id', $businessId)
                ->where('product_id', $line['product_id'])
                ->exists();

            if (!$hasBatches) {
                continue; // Not a pharmacy item or no batches tracked
            }

            $requiredQty = $line['quantity'];

            // FEFO Algorithm: Oldest Expiry First
            $batches = DB::table('pharmacy_batches')
                ->where('business_id', $businessId)
                ->where('product_id', $line['product_id'])
                ->where('quantity_available', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate() // Prevent race conditions on batch
                ->get();

            if ($batches->isEmpty()) {
                throw new \Exception("Out of stock for Pharmacy Product ID {$line['product_id']}");
            }

            foreach ($batches as $batch) {
                if ($requiredQty <= 0) break;

                // HARD STOP: Never sell expired medication
                if ($batch->expiry_date < now()->toDateString()) {
                    Log::critical("Pharmacy FEFO Violation Attempt: Batch {$batch->batch_number} is expired.");
                    throw new ExpiredStockException("Cannot dispense expired medication from Batch {$batch->batch_number}");
                }

                $deduct = min($batch->quantity_available, $requiredQty);
                
                DB::table('pharmacy_batches')
                    ->where('id', $batch->id)
                    ->decrement('quantity_available', $deduct);

                // Note: The core hasn't finalized the line ID yet during 'TransactionProcessing', 
                // so we insert a null line_id or link it via a post-processing event in a real environment.
                // For this test, we just write the deduction.
                DB::table('pharmacy_batch_transactions')->insert([
                    'batch_id' => $batch->id,
                    'transaction_line_id' => $line['transaction_line_id'] ?? 0,
                    'quantity_deducted' => $deduct,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $requiredQty -= $deduct;
            }

            if ($requiredQty > 0) {
                throw new \Exception("Insufficient valid batch stock for Pharmacy Product ID {$line['product_id']}");
            }
        }
    }
}
