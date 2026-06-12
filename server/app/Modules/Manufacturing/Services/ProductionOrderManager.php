<?php

namespace App\Modules\Manufacturing\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Manufacturing\Exceptions\InvalidStateException;
use App\Modules\Manufacturing\Exceptions\InsufficientStockException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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

            $totalRawMaterialCost = BigDecimal::zero();
            $totalScrapCost = BigDecimal::zero();

            // 1. Process Material Consumption and FIFO Locks
            foreach ($lines as $line) {
                $qtyToConsume = BigDecimal::of($line->quantity_required);
                
                // Add scrap qty to required consumption if any
                $scrapQty = BigDecimal::zero();
                foreach ($scrapPayload as $scrap) {
                    if ($scrap['raw_material_id'] == $line->raw_material_id) {
                        $scrapQty = $scrapQty->plus(BigDecimal::of($scrap['qty']));
                    }
                }
                
                $totalQtyNeeded = $qtyToConsume->plus($scrapQty);
                $remainingToConsume = clone $totalQtyNeeded;

                // FIFO Lock Layer
                $purchaseLines = DB::table('purchase_lines')
                    ->where('product_id', $line->raw_material_id)
                    ->where('quantity_sold', '<', DB::raw('quantity'))
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $lineAccumulatedCost = BigDecimal::zero();
                $lineScrapCost = BigDecimal::zero();

                foreach ($purchaseLines as $pl) {
                    if ($remainingToConsume->isLessThanOrEqualTo(0)) break;

                    $qtyAvailable = BigDecimal::of($pl->quantity)->minus(BigDecimal::of($pl->quantity_sold));
                    $consumeFromLayer = ($qtyAvailable->isGreaterThanOrEqualTo($remainingToConsume)) ? clone $remainingToConsume : clone $qtyAvailable;

                    // Update Purchase Line Sold Qty
                    DB::table('purchase_lines')
                        ->where('id', $pl->id)
                        ->update([
                            'quantity_sold' => DB::raw("quantity_sold + " . $consumeFromLayer->toScale(4)->__toString())
                        ]);

                    $layerCost = $consumeFromLayer->multipliedBy(BigDecimal::of($pl->purchase_price));

                    // Distribute cost between raw mat and scrap
                    $layerScrapQty = ($consumeFromLayer->isGreaterThanOrEqualTo($scrapQty)) ? clone $scrapQty : clone $consumeFromLayer;
                    $layerRawQty = $consumeFromLayer->minus($layerScrapQty);
                    
                    $scrapQty = $scrapQty->minus($layerScrapQty);
                    
                    $layerScrapCost = $layerScrapQty->multipliedBy(BigDecimal::of($pl->purchase_price));
                    $layerRawCost = $layerRawQty->multipliedBy(BigDecimal::of($pl->purchase_price));

                    $lineScrapCost = $lineScrapCost->plus($layerScrapCost);
                    $lineAccumulatedCost = $lineAccumulatedCost->plus($layerRawCost);

                    $remainingToConsume = $remainingToConsume->minus($consumeFromLayer);
                }

                if ($remainingToConsume->isGreaterThan(0)) {
                    throw new InsufficientStockException("Not enough stock for material ID {$line->raw_material_id}");
                }

                $totalRawMaterialCost = $totalRawMaterialCost->plus($lineAccumulatedCost);
                $totalScrapCost = $totalScrapCost->plus($lineScrapCost);

                DB::table('production_order_lines')
                    ->where('id', $line->id)
                    ->update([
                        'quantity_consumed' => $qtyToConsume->toScale(4)->__toString(),
                        'accumulated_cost' => $lineAccumulatedCost->toScale(4)->__toString(),
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
            $overheadCost = BigDecimal::of($order->overhead_cost ?? '0.0000');
            $totalAccumulatedCost = $totalRawMaterialCost->plus($totalScrapCost)->plus($overheadCost);
            $finalUnitCost = $totalAccumulatedCost->dividedBy(BigDecimal::of($order->quantity_planned), 4, RoundingMode::HALF_UP);

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
                    'final_unit_cost' => $finalUnitCost->toScale(4)->__toString(),
                    'updated_at' => now()
                ]);

            return [
                'final_unit_cost' => $finalUnitCost->toScale(4)->__toString(),
                'total_cost' => $totalAccumulatedCost->toScale(4)->__toString()
            ];
        });
    }
}
