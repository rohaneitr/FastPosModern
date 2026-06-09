<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryLayer;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;

class ReconcileNegativeLayersAction
{
    /**
     * Executes the Automated Negative Stock True-Up Engine.
     * When new stock arrives via PO, it automatically extinguishes negative debt layers.
     * If there's a cost variance between the new PO cost and the old fallback cost,
     * it immediately posts the variance delta to the Double Entry Ledger.
     * @return string The remaining incoming quantity after extinguishing debt.
     */
    public function execute(int $businessId, int $productId, $incomingQty, $incomingUnitCost, int $purchaseId, int $userId = null): string
    {
        $bdIncomingQty = BigDecimal::of($incomingQty);
        $bdIncomingCost = BigDecimal::of($incomingUnitCost);

        if ($bdIncomingQty->isLessThanOrEqualTo(0)) {
            return '0.0000';
        }

        // Lock negative layers for this product
        $negativeLayers = InventoryLayer::where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('remaining_qty', '<', 0)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        if ($negativeLayers->isEmpty()) {
            return $bdIncomingQty->toScale(4)->__toString();
        }

        $totalVariance = BigDecimal::zero();
        $updates = [];

        foreach ($negativeLayers as $layer) {
            if ($bdIncomingQty->isZero()) {
                break;
            }

            // negative layer remaining_qty is negative. To get the magnitude of debt:
            $layerDebt = BigDecimal::of($layer->remaining_qty)->abs();
            $layerCost = BigDecimal::of($layer->unit_cost);

            if ($bdIncomingQty->isGreaterThanOrEqualTo($layerDebt)) {
                // Incoming quantity fully extinguishes this negative layer
                $qtyExtinguished = clone $layerDebt;
                $bdIncomingQty = $bdIncomingQty->minus($qtyExtinguished);

                $updates[] = [
                    'id' => $layer->id,
                    'remaining_qty' => '0.0000',
                ];
            } else {
                // Incoming quantity partially extinguishes this negative layer
                $qtyExtinguished = clone $bdIncomingQty;
                $newRemaining = BigDecimal::of($layer->remaining_qty)->plus($qtyExtinguished);
                $bdIncomingQty = BigDecimal::zero();

                $updates[] = [
                    'id' => $layer->id,
                    'remaining_qty' => $newRemaining->toScale(4)->__toString(),
                ];
            }

            // Calculate Variance: (Incoming Unit Cost - Negative Unit Cost) * Qty Extinguished
            $variancePerUnit = $bdIncomingCost->minus($layerCost);
            $layerVariance = $variancePerUnit->multipliedBy($qtyExtinguished);
            
            $totalVariance = $totalVariance->plus($layerVariance);
        }

        foreach ($updates as $update) {
            DB::table('inventory_layers')->where('id', $update['id'])
                ->update([
                    'remaining_qty' => $update['remaining_qty'],
                    'updated_at' => now(),
                ]);
        }

        // Post the Cost Variance to the General Ledger
        if (!$totalVariance->isZero()) {
            $varianceAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE);
            $inventoryAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);

            $debits = [];
            $credits = [];

            if ($totalVariance->isGreaterThan(0)) {
                // Under-costed before: We need to increase COGS/Variance, and decrease Inventory Asset
                $debits[] = ['chart_of_account_id' => $varianceAccountId, 'amount' => (string) $totalVariance];
                $credits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $totalVariance];
            } else {
                // Over-costed before: We need to decrease COGS/Variance (Credit), and increase Inventory Asset (Debit)
                $absVariance = $totalVariance->abs();
                $debits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $absVariance];
                $credits[] = ['chart_of_account_id' => $varianceAccountId, 'amount' => (string) $absVariance];
            }

            $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);
            $ledger->recordEntry(
                $businessId,
                'TRUE-UP-' . time() . '-' . mt_rand(100, 999),
                now()->toDateString(),
                "Inventory Negative Stock True-Up Variance",
                $debits,
                $credits,
                $purchaseId,
                'purchase_trueup',
                $userId ?? (auth()->id() ?? DB::table('users')->where('id', '>', 0)->first()->id)
            );
        }

        return $bdIncomingQty->toScale(4)->__toString();
    }
}
