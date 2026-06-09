<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryLayer;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ConsumeBatchFIFOInventoryAction
{
    /**
     * Consumes inventory based on strict chronological FIFO ordering for multiple products.
     * Uses a single atomic lock query (N+1 safe).
     * Automatically generates temporary negative layers if sales exceed physical layers.
     * 
     * @param int $businessId
     * @param array $productQuantities Array of ['product_id' => qty] to consume.
     * @return array ['product_id' => string_cogs_value]
     */
    public function execute(int $businessId, array $productQuantities): array
    {
        $results = [];
        $productIds = array_keys($productQuantities);

        if (empty($productIds)) {
            return $results;
        }

        // 1. Sort product IDs deterministically to mathematically eliminate SQL deadlock conditions
        sort($productIds);

        // 2. Fetch all active layers for all products in cart with a SINGLE lock query.
        $layers = InventoryLayer::where('business_id', $businessId)
            ->whereIn('product_id', $productIds)
            ->where('remaining_qty', '>', 0)
            ->orderBy('product_id')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->groupBy('product_id');

        // Fetch base products info for fallbacks (Negative Stock Outbreak Protection)
        $products = DB::table('products')->whereIn('id', $productIds)->get()->keyBy('id');

        $updates = [];
        $inserts = [];

        foreach ($productQuantities as $productId => $quantityToConsume) {
            $bdQtyToConsume = BigDecimal::of($quantityToConsume);
            $totalCogs = BigDecimal::zero();

            if ($bdQtyToConsume->isLessThanOrEqualTo(0)) {
                $results[$productId] = '0.0000';
                continue;
            }

            $productLayers = $layers->get($productId, collect());

            foreach ($productLayers as $layer) {
                if ($bdQtyToConsume->isZero()) break;

                $layerQty = BigDecimal::of($layer->remaining_qty);
                $layerCost = BigDecimal::of($layer->unit_cost);

                if ($layerQty->isGreaterThanOrEqualTo($bdQtyToConsume)) {
                    // This layer can absorb the entire remaining sale quantity
                    $totalCogs = $totalCogs->plus($layerCost->multipliedBy($bdQtyToConsume));
                    
                    $newLayerQty = $layerQty->minus($bdQtyToConsume)->toScale(4)->__toString();
                    $updates[] = [
                        'id' => $layer->id,
                        'remaining_qty' => $newLayerQty,
                    ];
                    
                    $bdQtyToConsume = BigDecimal::zero();
                } else {
                    // The sale exhausts this entire layer and needs more
                    $totalCogs = $totalCogs->plus($layerCost->multipliedBy($layerQty));
                    $bdQtyToConsume = $bdQtyToConsume->minus($layerQty);
                    
                    $updates[] = [
                        'id' => $layer->id,
                        'remaining_qty' => '0.0000',
                    ];
                }
            }

            // 2. Negative Stock Outbreak Protection
            if ($bdQtyToConsume->isGreaterThan(0)) {
                $product = $products->get($productId);
                $baseCost = $product ? BigDecimal::of($product->purchase_price) : BigDecimal::zero();
                
                $totalCogs = $totalCogs->plus($baseCost->multipliedBy($bdQtyToConsume));
                
                // Spawn a Negative Cost Layer
                $inserts[] = [
                    'business_id' => $businessId,
                    'product_id' => $productId,
                    'purchase_line_id' => null,
                    'original_qty' => $bdQtyToConsume->negated()->toScale(4)->__toString(),
                    'remaining_qty' => $bdQtyToConsume->negated()->toScale(4)->__toString(),
                    'unit_cost' => $baseCost->toScale(4)->__toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $results[$productId] = $totalCogs->toScale(4, RoundingMode::HALF_UP)->__toString();
        }

        // Apply bulk database changes
        if (!empty($updates)) {
            // Using a simple loop for updates since it's already locked and small,
            // or we could use Laravel's upsert. We'll use a loop for simplicity and safety.
            foreach ($updates as $update) {
                DB::table('inventory_layers')
                    ->where('id', $update['id'])
                    ->update(['remaining_qty' => $update['remaining_qty'], 'updated_at' => now()]);
            }
        }

        if (!empty($inserts)) {
            DB::table('inventory_layers')->insert($inserts);
        }

        return $results;
    }
}
