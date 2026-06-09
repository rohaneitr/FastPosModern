<?php

namespace App\Modules\Imports\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Modules\Imports\Models\ImportStatus;
use App\Modules\Imports\Exceptions\RowValidationException;
use App\Modules\Catalog\Models\Product;
use Brick\Math\BigDecimal;
use Exception;

class ProcessProductImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $businessId;
    protected int $importStatusId;
    protected array $chunkData;
    protected int $startRowIndex;

    public function __construct(int $businessId, int $importStatusId, array $chunkData, int $startRowIndex)
    {
        $this->businessId = $businessId;
        $this->importStatusId = $importStatusId;
        $this->chunkData = $chunkData;
        $this->startRowIndex = $startRowIndex;
    }

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $importStatus = ImportStatus::find($this->importStatusId);
        if (!$importStatus) {
            return;
        }

        try {
            DB::beginTransaction();

            $successCount = 0;
            $skusInChunk = [];

            foreach ($this->chunkData as $index => $row) {
                $actualRowNumber = $this->startRowIndex + $index;

                // 1. Memory Validation
                if (empty($row['name'])) {
                    throw new RowValidationException($actualRowNumber, "Product name is required.");
                }

                if (empty($row['sku'])) {
                    throw new RowValidationException($actualRowNumber, "SKU is required.");
                }

                if (in_array($row['sku'], $skusInChunk)) {
                    throw new RowValidationException($actualRowNumber, "Duplicate SKU within the same import chunk: {$row['sku']}");
                }
                $skusInChunk[] = $row['sku'];

                // Enforce Tenant Context for Uniqueness
                $exists = DB::table('products')
                    ->where('business_id', $this->businessId)
                    ->where('sku', $row['sku'])
                    ->exists();

                if ($exists) {
                    throw new RowValidationException($actualRowNumber, "SKU already exists in catalog: {$row['sku']}");
                }

                // Numeric Precision Validation
                $price = $row['price'] ?? '0';
                $cost = $row['cost'] ?? '0';
                
                try {
                    $bdPrice = BigDecimal::of($price);
                    $bdCost = BigDecimal::of($cost);
                } catch (\Exception $e) {
                    throw new RowValidationException($actualRowNumber, "Invalid numeric format for price/cost.");
                }
                
                if ($bdPrice->isNegative() || $bdCost->isNegative()) {
                    throw new RowValidationException($actualRowNumber, "Prices and costs cannot be negative.");
                }

                // 2. Insert Execution (Strictly bound to Tenant)
                DB::table('products')->insert([
                    'business_id' => $this->businessId,
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'selling_price' => $bdPrice->toScale(4)->__toString(),
                    'purchase_price' => $bdCost->toScale(4)->__toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $successCount++;
            }

            // If we reach here, all rows in the chunk are valid.
            DB::commit();

            // Update Progress
            $importStatus->increment('processed_rows', count($this->chunkData));
            $importStatus->increment('successful_rows', $successCount);

        } catch (RowValidationException $e) {
            // Atomic failure: Rollback the ENTIRE chunk
            DB::rollBack();

            // Append error safely
            $errors = $importStatus->errors ?? [];
            $errors[$e->getRowNumber()] = $e->getMessage();
            
            $importStatus->update([
                'errors' => $errors,
                'processed_rows' => $importStatus->processed_rows + count($this->chunkData),
                'failed_rows' => $importStatus->failed_rows + count($this->chunkData),
            ]);

            // Note: We DO NOT throw the exception. 
            // We want the rest of the batch to continue executing for other pristine chunks.

        } catch (Exception $e) {
            DB::rollBack();
            
            $errors = $importStatus->errors ?? [];
            $errors["chunk_start_{$this->startRowIndex}"] = "System Error: " . $e->getMessage();
            
            $importStatus->update([
                'errors' => $errors,
                'processed_rows' => $importStatus->processed_rows + count($this->chunkData),
                'failed_rows' => $importStatus->failed_rows + count($this->chunkData),
            ]);
        }
    }
}
