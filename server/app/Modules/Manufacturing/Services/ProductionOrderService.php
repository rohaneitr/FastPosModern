<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Inventory\Services\BOMDeductionService;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductionOrderService
{
    protected BOMDeductionService $bomDeductionService;

    public function __construct(BOMDeductionService $bomDeductionService)
    {
        $this->bomDeductionService = $bomDeductionService;
    }

    /**
     * Completes a production order, consuming raw materials and creating finished goods inventory.
     */
    public function completeOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('production_orders')->where('id', $orderId)->lockForUpdate()->first();
            
            if (!$order) {
                throw new Exception("Production Order not found.");
            }

            if ($order->status === 'Completed') {
                throw new Exception("Production Order is already completed.");
            }

            // 1. Calculate and Deduct Raw Materials (BOM Explosion)
            // This will recursively deduct ingredients and return the exact total COGS.
            $rawMaterialCogs = $this->bomDeductionService->deductForOrder(
                $order->business_id,
                $order->product_id,
                $order->quantity,
                "Production #{$order->order_number}"
            );

            // 2. Cost Roll-up
            // Total Production Cost = Raw Material COGS + Labor + Overhead
            $laborCost = $order->labor_cost ?? '0.0000';
            $overheadCost = $order->overhead_cost ?? '0.0000';
            
            $totalProductionCost = bcadd($rawMaterialCogs, bcadd($laborCost, $overheadCost, 4), 4);
            $unitCost = bcdiv($totalProductionCost, (string)$order->quantity, 4);

            // 3. Inject Finished Good into Inventory Ledger (FIFO/FEFO Layer)
            // Generate standard manufacturing Expiry/Lot tracking if applicable.
            $lotNumber = "LOT-{$order->order_number}";
            
            DB::table('inventory_layers')->insert([
                'business_id' => $order->business_id,
                'product_id' => $order->product_id,
                'original_qty' => $order->quantity,
                'remaining_qty' => $order->quantity,
                'unit_cost' => $unitCost,
                'lot_number' => $lotNumber,
                'expiry_date' => $order->expiry_date, // Pre-calculated based on shelf-life rules
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Update Production Order Status
            DB::table('production_orders')->where('id', $orderId)->update([
                'status' => 'Completed',
                'total_material_cost' => $rawMaterialCogs,
                'total_production_cost' => $totalProductionCost,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Stock History Audit for Finished Good
            DB::table('stock_history')->insert([
                'business_id' => $order->business_id,
                'product_id' => $order->product_id,
                'quantity_changed' => $order->quantity, // Positive denotes yield addition
                'transaction_type' => 'production_yield',
                'reference' => "Production #{$order->order_number}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
