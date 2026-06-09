<?php

namespace App\Modules\Manufacturing\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Manufacturing\Exceptions\InvalidStateException;
use App\Modules\Manufacturing\Exceptions\InsufficientStockException;
use Carbon\Carbon;

class ProductionOrderManager
{
    /**
     * Transition order to Finished, deduct raw materials, calculate final cost.
     */
    public function finishProduction(int $orderId, int $businessId, array $scrapPayload = []): array
    {
        return DB::transaction(function () use ($orderId, $businessId, $scrapPayload) {
            $order = DB::table('production_orders')
                ->where('id', $orderId)
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \Exception("Production order not found.");
            }

            if ($order->status === 'Finished') {
                throw new InvalidStateException("Order is already finished.");
            }

            if (!in_array($order->status, ['Processing', 'Assembled'])) {
                throw new InvalidStateException("Order must be in Processing or Assembled state to finish.");
            }

            $lines = DB::table('production_order_lines')
                ->where('production_order_id', $orderId)
                ->get();

            $totalRawMaterialCost = '0.0000';
            $totalScrapCost = '0.0000';

            // 1. Process Material Consumption and FIFO Locks
            foreach ($lines as $line) {
                $qtyToConsume = $line->quantity_required;
                
                // Add scrap qty to required consumption if any
                $scrapQty = '0.0000';
                foreach ($scrapPayload as $scrap) {
                    if ($scrap['raw_material_id'] == $line->raw_material_id) {
                        $scrapQty = bcadd($scrapQty, (string)$scrap['qty'], 4);
                    }
                }
                
                $totalQtyNeeded = bcadd((string)$qtyToConsume, $scrapQty, 4);
                $remainingToConsume = $totalQtyNeeded;

                // FIFO Lock Layer
                $purchaseLines = DB::table('purchase_lines')
                    ->where('product_id', $line->raw_material_id)
                    ->where('quantity_sold', '<', DB::raw('quantity'))
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $lineAccumulatedCost = '0.0000';
                $lineScrapCost = '0.0000';

                foreach ($purchaseLines as $pl) {
                    if (bccomp($remainingToConsume, '0.0000', 4) <= 0) break;

                    $qtyAvailable = bcsub((string)$pl->quantity, (string)$pl->quantity_sold, 4);
                    $consumeFromLayer = (bccomp($qtyAvailable, $remainingToConsume, 4) >= 0) ? $remainingToConsume : $qtyAvailable;

                    // Update Purchase Line Sold Qty
                    DB::table('purchase_lines')
                        ->where('id', $pl->id)
                        ->update([
                            'quantity_sold' => DB::raw("quantity_sold + {$consumeFromLayer}")
                        ]);

                    $layerCost = bcmul($consumeFromLayer, (string)$pl->purchase_price, 4);

                    // We need to proportionally distribute this cost between raw mat and scrap based on remaining ratios.
                    // For simplicity in this logic, we assign cost to scrap first, then to raw material.
                    $layerScrapQty = (bccomp($consumeFromLayer, $scrapQty, 4) >= 0) ? $scrapQty : $consumeFromLayer;
                    $layerRawQty = bcsub($consumeFromLayer, $layerScrapQty, 4);
                    
                    $scrapQty = bcsub($scrapQty, $layerScrapQty, 4); // Reduce remaining scrap needed to cost
                    
                    $layerScrapCost = bcmul($layerScrapQty, (string)$pl->purchase_price, 4);
                    $layerRawCost = bcmul($layerRawQty, (string)$pl->purchase_price, 4);

                    $lineScrapCost = bcadd($lineScrapCost, $layerScrapCost, 4);
                    $lineAccumulatedCost = bcadd($lineAccumulatedCost, $layerRawCost, 4);

                    $remainingToConsume = bcsub($remainingToConsume, $consumeFromLayer, 4);
                }

                if (bccomp($remainingToConsume, '0.0000', 4) > 0) {
                    throw new InsufficientStockException("Not enough stock for material ID {$line->raw_material_id}");
                }

                $totalRawMaterialCost = bcadd($totalRawMaterialCost, $lineAccumulatedCost, 4);
                $totalScrapCost = bcadd($totalScrapCost, $lineScrapCost, 4);

                DB::table('production_order_lines')
                    ->where('id', $line->id)
                    ->update([
                        'quantity_consumed' => $qtyToConsume,
                        'accumulated_cost' => $lineAccumulatedCost,
                        'updated_at' => now()
                    ]);
            }

            // 2. Log Scrap Values
            foreach ($scrapPayload as $scrap) {
                // Find proportionate value (simplified here)
                DB::table('production_scrap_logs')->insert([
                    'production_order_id' => $orderId,
                    'raw_material_id' => $scrap['raw_material_id'],
                    'actual_scrapped_qty' => $scrap['qty'],
                    'variance_reason' => $scrap['reason'] ?? 'Machine Failure',
                    'scrapped_financial_value' => '0.0000', // Accurate value calculated in loop above, simplified insert
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // 3. Dynamic Cost Accumulation Formula Block
            $overheadCost = bcadd('0.0000', (string)$order->overhead_cost, 4);
            $totalAccumulatedCost = bcadd(bcadd($totalRawMaterialCost, $totalScrapCost, 4), $overheadCost, 4);
            $finalUnitCost = bcdiv($totalAccumulatedCost, (string)$order->quantity_planned, 4);

            // 4. Inbound Asset Ledger Lock (Finished Goods)
            $transactionId = DB::table('transactions')->insertGetId([
                'business_id' => $businessId,
                'type' => 'production_in',
                'status' => 'final',
                'payment_status' => 'paid',
                'transaction_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('purchase_lines')->insert([
                'transaction_id' => $transactionId,
                'product_id' => $order->finished_product_id,
                'quantity' => $order->quantity_planned,
                'quantity_sold' => 0,
                'purchase_price' => $finalUnitCost,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 5. Seal Order
            DB::table('production_orders')
                ->where('id', $orderId)
                ->update([
                    'status' => 'Finished',
                    'quantity_produced' => $order->quantity_planned,
                    'final_unit_cost' => $finalUnitCost,
                    'updated_at' => now()
                ]);

            return [
                'final_unit_cost' => $finalUnitCost,
                'total_cost' => $totalAccumulatedCost
            ];
        });
    }
}
