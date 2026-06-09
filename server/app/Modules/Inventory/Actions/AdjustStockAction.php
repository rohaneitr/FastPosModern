<?php

namespace App\Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Exception;

class AdjustStockAction
{
    /**
     * Safely adjust product stock preventing concurrency and insert anomalies.
     *
     * @param int $businessId The Tenant ID
     * @param int $userId The User ID performing the action
     * @param int $productId The Product ID
     * @param int $locationId The Location ID
     * @param float|int $quantity The quantity to adjust (positive or negative)
     * @param string|null $reason The reason for adjustment
     * @return array
     * @throws Exception
     */
    public function execute(int $businessId, int $userId, int $productId, int $locationId, $quantity, ?string $reason): array
    {
        return DB::transaction(function () use ($businessId, $userId, $productId, $locationId, $quantity, $reason) {
            // 1. Lock the parent product to serialize requests for the same product and prevent Insert Anomalies
            $product = DB::table('products')
                ->where('id', $productId)
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new Exception("Product not found or access denied for this tenant.");
            }

            // Verify location belongs to the same tenant
            $location = DB::table('locations')
                ->where('id', $locationId)
                ->where('business_id', $businessId)
                ->first();

            if (!$location) {
                throw new Exception("Location not found or access denied for this tenant.");
            }

            // 2. Fetch the stock record (no need to lockForUpdate here because parent product is already locked)
            $stock = DB::table('product_stocks')
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->first();

            $qtyBefore = \App\Modules\Sales\Services\FinancialCalculator::of($stock ? $stock->qty_available : 0);
            $qtyAfter = \App\Modules\Sales\Services\FinancialCalculator::add($qtyBefore, $quantity);

            if ($qtyAfter->isNegative()) {
                throw new Exception('Stock adjustment would result in negative inventory.');
            }

            $qtyBeforeStr = (string) $qtyBefore;
            $qtyAfterStr = (string) $qtyAfter;

            // 3. Upsert the stock value
            if ($stock) {
                DB::table('product_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'qty_available' => $qtyAfterStr,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('product_stocks')->insert([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'qty_available' => $qtyAfterStr,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Record Audit Log
            DB::table('stock_adjustments')->insert([
                'product_id' => $productId,
                'location_id' => $locationId,
                'adjusted_by' => $userId,
                'quantity' => (string) \App\Modules\Sales\Services\FinancialCalculator::of($quantity),
                'qty_before' => $qtyBeforeStr,
                'qty_after' => $qtyAfterStr,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Zero-Trust Forensic Audit Trail
            $auditService = app(\App\Modules\Security\Services\ForensicAuditService::class);
            $auditService->snapshot(
                'ProductStock',
                $stock ? $stock->id : $productId, // Fallback to product ID if no stock ID
                'stock_adjustment',
                'adjust_stock',
                $stock ? ['qty_available' => $qtyBeforeStr] : null,
                ['qty_available' => $qtyAfterStr],
                request()->path() ?? 'cli'
            );

            return [
                'message' => 'Stock adjusted successfully',
                'qty_before' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($qtyBefore),
                'qty_after' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($qtyAfter),
            ];
        });
    }
}
