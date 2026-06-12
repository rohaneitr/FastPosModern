<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryLayer;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class RestoreFIFOLayerAction
{
    /**
     * Spawns an RMA Return Cost Layer to maintain precise FIFO tracking
     * when a product is returned.
     * 
     * @param int $businessId
     * @param int $productId
     * @param float|string $returnQty
     */
    public function execute(int $businessId, int $productId, $returnQty): void
    {
        $bdReturnQty = BigDecimal::of($returnQty);
        
        if ($bdReturnQty->isLessThanOrEqualTo(0)) {
            return;
        }

        // We fetch the product to get its baseline cost.
        // In a more complex ledger, we would trace the exact purchase line, 
        // but for RMA, spawning a new layer with the baseline purchase_price maintains pristine asset history
        // and handles blended/mixed cost returns cleanly.
        $product = DB::table('products')->where('id', $productId)->where('business_id', $businessId)->first();
        $unitCost = $product ? BigDecimal::of($product->purchase_price) : BigDecimal::zero();

        $qtyStr = $bdReturnQty->toScale(4)->__toString();

        InventoryLayer::create([
            'business_id' => $businessId,
            'product_id' => $productId,
            'purchase_line_id' => null, // Represents an RMA orphaned layer
            'original_qty' => $qtyStr,
            'remaining_qty' => $qtyStr,
            'unit_cost' => $unitCost->toScale(4)->__toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
