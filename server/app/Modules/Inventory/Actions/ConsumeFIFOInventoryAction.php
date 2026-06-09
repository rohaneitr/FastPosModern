<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryLayer;
use App\Modules\Inventory\Models\Product;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Support\Facades\DB;

class ConsumeFIFOInventoryAction
{
    /**
     * Consumes inventory based on strict chronological FIFO ordering and calculates the exact blended COGS string.
     * 
     * @param int $businessId
     * @param int $productId
     * @param string $quantityToConsume
     * @return string $exactCogs The sum of consumed layer costs.
     */
    public function execute(int $businessId, int $productId, string $quantityToConsume): string
    {
        $bdQuantityToConsume = BigDecimal::of($quantityToConsume);
        $totalCogs = BigDecimal::zero();

        if ($bdQuantityToConsume->isLessThanOrEqualTo(0)) {
            return '0.0000';
        }

        // 1. Fetch Active Layers sorted by Created_At ascending (Oldest First)
        // We strictly lock the rows to prevent concurrent checkout race conditions
        $layers = InventoryLayer::where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc') // Tie-breaker for batch inserts
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            $layerQty = BigDecimal::of($layer->remaining_qty);
            $layerCost = BigDecimal::of($layer->unit_cost);

            if ($bdQuantityToConsume->isZero()) {
                break;
            }

            if ($layerQty->isGreaterThanOrEqualTo($bdQuantityToConsume)) {
                // This single layer can absorb the entire remaining sale quantity
                $cogsFromThisLayer = $layerCost->multipliedBy($bdQuantityToConsume);
                $totalCogs = $totalCogs->plus($cogsFromThisLayer);

                // Reduce layer
                $newLayerQty = $layerQty->minus($bdQuantityToConsume);
                $layer->update(['remaining_qty' => $newLayerQty->toScale(4)->__toString()]);

                $bdQuantityToConsume = BigDecimal::zero(); // Finished
            } else {
                // The sale exhausts this entire layer and needs more
                $cogsFromThisLayer = $layerCost->multipliedBy($layerQty);
                $totalCogs = $totalCogs->plus($cogsFromThisLayer);

                $bdQuantityToConsume = $bdQuantityToConsume->minus($layerQty);
                
                // Zero out layer
                $layer->update(['remaining_qty' => '0.0000']);
            }
        }

        // 2. Fallback Mechanism for Negative Inventory (Selling before receiving PO)
        if ($bdQuantityToConsume->isGreaterThan(0)) {
            // If layers ran out but we are still trying to deduct stock, fall back to the base product cost
            $product = DB::table('products')->where('id', $productId)->first();
            $baseCost = $product ? BigDecimal::of($product->purchase_price) : BigDecimal::zero();

            $fallbackCogs = $baseCost->multipliedBy($bdQuantityToConsume);
            $totalCogs = $totalCogs->plus($fallbackCogs);

            // Note: We do not create a negative layer here. We simply deduct the master stock later.
            // The COGS is estimated based on the last known purchase_price fallback.
        }

        return $totalCogs->toScale(4, RoundingMode::HALF_UP)->__toString();
    }
}
