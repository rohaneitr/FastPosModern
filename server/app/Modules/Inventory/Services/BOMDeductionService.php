<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction;
use Illuminate\Support\Facades\DB;
use Exception;
use Brick\Math\BigDecimal;

class BOMDeductionService
{
    protected ConsumeBatchFIFOInventoryAction $consumeFIFOAction;

    public function __construct(ConsumeBatchFIFOInventoryAction $consumeFIFOAction)
    {
        $this->consumeFIFOAction = $consumeFIFOAction;
    }

    /**
     * Deducts inventory for a product. If it's composite, it recurses through its BOM.
     * Wrapped in a DB::transaction to ensure absolute atomicity.
     * Returns the total Cost of Goods Sold (COGS) for the deducted materials.
     */
    public function deductForOrder(int $businessId, int $productId, $quantity, string $reference): string
    {
        return DB::transaction(function () use ($businessId, $productId, $quantity, $reference) {
            return $this->recurseAndDeduct($businessId, $productId, $quantity, $reference);
        });
    }

    private function recurseAndDeduct(int $businessId, int $productId, $quantity, string $reference, array $visitedPaths = []): string
    {
        if (in_array($productId, $visitedPaths)) {
            $path = implode(' -> ', $visitedPaths) . " -> {$productId}";
            throw new Exception("Circular dependency detected in BOM: {$path}");
        }
        $visitedPaths[] = $productId;
        $product = DB::table('products')->where('id', $productId)->first();
        if (!$product) {
            throw new Exception("Product ID {$productId} not found.");
        }

        $totalCogs = BigDecimal::zero();

        if ($product->type === 'composite') {
            // Fetch Bill of Materials (BOM)
            $assemblies = DB::table('product_assemblies')
                ->where('parent_product_id', $productId)
                ->get();

            if ($assemblies->isEmpty()) {
                throw new Exception("Composite product {$product->name} has no BOM defined.");
            }

            $bdQuantity = BigDecimal::of($quantity);

            foreach ($assemblies as $assembly) {
                // PHASE 2: Precise fractional multiplication using BigDecimal
                $bdAssemblyQty = BigDecimal::of($assembly->quantity);
                $requiredQty = $bdQuantity->multipliedBy($bdAssemblyQty)->toScale(4)->__toString();
                
                // Recurse into child
                $childCogs = $this->recurseAndDeduct($businessId, $assembly->child_product_id, $requiredQty, $reference, $visitedPaths);
                $totalCogs = $totalCogs->plus(BigDecimal::of($childCogs));
            }
            return $totalCogs->toScale(4)->__toString();
        } else {
            // Standard product or raw material -> Deduct via FEFO/FIFO Engine
            
            // Check if sufficient stock exists globally across all layers before attempting deduction.
            $totalAvailable = DB::table('inventory_layers')
                ->where('business_id', $businessId)
                ->where('product_id', $productId)
                ->sum('remaining_qty');

            if (bccomp((string)$totalAvailable, (string)$quantity, 4) === -1) {
                throw new Exception("Insufficient stock for raw material ID {$productId}. Required: {$quantity}, Available: {$totalAvailable}");
            }

            $productQuantities = [
                $productId => $quantity
            ];

            // Execute the Unified FIFO/FEFO deduction
            $results = $this->consumeFIFOAction->execute($businessId, $productQuantities);
            
            $totalCogs = $results[$productId] ?? '0.0000';

            // Audit the transaction
            DB::table('stock_history')->insert([
                'business_id' => $businessId,
                'product_id' => $productId,
                'quantity_changed' => -$quantity, // Negative denotes deduction
                'transaction_type' => 'production_usage',
                'reference' => $reference,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $totalCogs;
    }
}
