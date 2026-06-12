<?php

namespace App\Modules\Procurement\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ReceivePurchaseService
 *
 * Extracted from PurchaseController::receive() (lines 20–163).
 *
 * This service handles the legacy "quick receive" flow which:
 *   1. Creates a minimal transaction record (type = 'purchase')
 *   2. For each line item, applies the WAC (Weighted Average Cost) formula
 *   3. Updates product_stocks quantity (pessimistic-locked)
 *   4. Updates cost baseline on variations and products tables
 *   5. Inserts stock_ledger entries (audit trail)
 *   6. Inserts transaction_lines
 *
 * WHY SEPARATE FROM ProcessPurchaseService?
 *   The receive() flow is a distinct, older pattern that writes to the
 *   `transactions` table directly (not the `purchases` table), and uses WAC
 *   costing rather than FIFO InventoryLayers. Merging them would create
 *   dangerous coupling between two different costing methodologies.
 *
 * WAC FORMULA:
 *   newMAC = (oldQty * oldMAC + newQty * newUnitCost) / (oldQty + newQty)
 *
 * DEADLOCK PREVENTION:
 *   Lines are sorted by product_id ASC before locking to enforce a consistent
 *   lock ordering across concurrent transactions.
 *
 * ZERO TRUST:
 *   - business_id always comes from the authenticated user, never from payload
 *   - pessimistic lock (lockForUpdate) prevents phantom reads during stock update
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.4
 * @version 2026-06-12
 */
class ReceivePurchaseService
{
    /**
     * Process a "quick receive" purchase order with WAC cost updating.
     *
     * @param int    $businessId
     * @param int    $userId
     * @param int    $locationId
     * @param int    $supplierId
     * @param string $referenceNo
     * @param array  $lines  [['product_id', 'variation_id'?, 'quantity', 'unit_cost'], ...]
     *
     * @return int  The new transaction_id
     *
     * @throws \Exception  Any DB failure — caller must handle
     */
    public function receive(
        int    $businessId,
        int    $userId,
        int    $locationId,
        int    $supplierId,
        string $referenceNo,
        array  $lines,
    ): int {
        return DB::transaction(function () use ($businessId, $userId, $locationId, $supplierId, $referenceNo, $lines) {
            $subtotal = array_sum(array_map(
                fn($l) => (float) $l['quantity'] * (float) $l['unit_cost'],
                $lines
            ));

            // ── 1. Create transaction header ──────────────────────────────────
            $transactionId = DB::table('transactions')->insertGetId([
                'business_id'      => $businessId,
                'location_id'      => $locationId,
                'contact_id'       => $supplierId,
                'created_by'       => $userId,
                'type'             => 'purchase',
                'status'           => 'received',
                'invoice_no'       => $referenceNo,
                'transaction_date' => Carbon::now(),
                'total_before_tax' => $subtotal,
                'final_total'      => $subtotal,
                'created_at'       => Carbon::now(),
                'updated_at'       => Carbon::now(),
            ]);

            // ── 2. Sort lines by product_id for deadlock prevention ───────────
            $sortedLines = $lines;
            usort($sortedLines, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

            // ── 3. Process each line ──────────────────────────────────────────
            $transactionLines = [];

            foreach ($sortedLines as $line) {
                $productId   = (int) $line['product_id'];
                $quantity    = (float) $line['quantity'];
                $newUnitCost = (float) $line['unit_cost'];

                // Pessimistic lock to prevent concurrent stock mutation
                $stock = DB::table('product_stocks')
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->lockForUpdate()
                    ->first();

                $oldQty = $stock ? (float) $stock->qty_available : 0.0;

                // Fetch current MAC from variation, fallback to product
                $variationQuery = DB::table('variations')->where('product_id', $productId);
                if (!empty($line['variation_id'])) {
                    $variationQuery->where('id', $line['variation_id']);
                }
                $variation = $variationQuery->first();
                $product   = DB::table('products')->where('id', $productId)->first();
                $oldMac    = $variation
                    ? (float) ($variation->default_purchase_price ?? 0)
                    : (float) ($product->purchase_price ?? 0);

                // WAC Formula
                $newTotalQty = $oldQty + $quantity;
                $newMac      = $newTotalQty > 0
                    ? (($oldQty * $oldMac) + ($quantity * $newUnitCost)) / $newTotalQty
                    : $newUnitCost;

                // Update or create stock row
                if ($stock) {
                    DB::table('product_stocks')
                        ->where('id', $stock->id)
                        ->update(['qty_available' => $newTotalQty, 'updated_at' => Carbon::now()]);
                } else {
                    DB::table('product_stocks')->insert([
                        'business_id'   => $businessId,
                        'location_id'   => $locationId,
                        'product_id'    => $productId,
                        'qty_available' => $newTotalQty,
                        'created_at'    => Carbon::now(),
                        'updated_at'    => Carbon::now(),
                    ]);
                }

                // Update cost baseline — use BigDecimal for precision
                $newMacStr = clone \Brick\Math\BigDecimal::of($newMac)->toScale(4)->__toString();

                if ($variation) {
                    DB::table('variations')
                        ->where('id', $variation->id)
                        ->update(['default_purchase_price' => $newMacStr]);
                }
                DB::table('products')
                    ->where('id', $productId)
                    ->update(['purchase_price' => $newMacStr]);

                // Stock ledger audit entry
                DB::table('stock_ledgers')->insert([
                    'business_id'      => $businessId,
                    'product_id'       => $productId,
                    'transaction_type' => 'purchase',
                    'quantity'         => $quantity,
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now(),
                ]);

                $transactionLines[] = [
                    'business_id'                => $businessId,
                    'transaction_id'             => $transactionId,
                    'product_id'                 => $productId,
                    'variation_id'               => $line['variation_id'] ?? null,
                    'quantity'                   => $quantity,
                    'unit_price_before_discount' => $newUnitCost,
                    'unit_price'                 => $newUnitCost,
                    'unit_price_inc_tax'         => $newUnitCost,
                    'item_tax'                   => 0,
                    'tax_rate'                   => 0,
                    'tax_amount'                 => 0,
                    'sourcing_status'            => 'ready',
                    'created_at'                 => Carbon::now(),
                    'updated_at'                 => Carbon::now(),
                ];
            }

            // Bulk insert all lines at once (more efficient than per-row)
            DB::table('transaction_lines')->insert($transactionLines);

            return $transactionId;
        });
    }
}
