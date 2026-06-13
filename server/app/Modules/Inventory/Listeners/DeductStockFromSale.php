<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Services\FinancialCalculator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * DeductStockFromSale — Inventory Domain Listener
 *
 * Handles stock deduction when a sale is completed.
 * Extracted from ProcessSaleService::deductInventoryForLine() and
 * ProcessSaleService::processSerialTracking().
 *
 * SYNCHRONOUS — NO ShouldQueue.
 *
 * WHY SYNCHRONOUS IS MANDATORY HERE:
 * This listener runs inside the same DB::transaction() as the sale itself.
 * If stock is insufficient for ANY line, this listener throws a
 * ValidationException, which propagates out of the transaction closure
 * and triggers a full rollback — the sale header, lines, and payment
 * records are all reverted. Queueing would break this guarantee entirely.
 *
 * RESPONSIBILITY BOUNDARY:
 * This listener ONLY touches `product_stocks`, `inventory_item_serials`,
 * and `transaction_item_serials`. It does NOT touch `transactions` or
 * `journal_entries`. This is strict domain isolation.
 *
 * COMPOSITE PRODUCT HANDLING:
 * Composite ("kit") products deduct from their child component stocks,
 * not the parent. Components with pending sourcing are skipped here —
 * they are handled by a separate procurement workflow.
 *
 * @version Phase 5 — Domain Event Decoupling
 */
class DeductStockFromSale
{
    /**
     * Handle the SaleCompleted event.
     *
     * @throws \Illuminate\Validation\ValidationException  — Insufficient stock
     * @throws \Exception                                  — Serial/IMEI violations
     */
    public function handle(SaleCompleted $event): void
    {
        // Only deduct stock for posted (final) invoices, never for drafts/quotations
        if (! $event->dto->isPosting) {
            return;
        }

        $dto                  = $event->dto;
        $sale                 = $event->sale;
        $enrichedItems        = $event->totals->enrichedItems;

        // ── Resolve product types and composite maps ───────────────────────────
        $productIds = collect($enrichedItems)->pluck('product_id')->unique()->values()->toArray();

        $products = DB::table('products')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $assemblies = DB::table('product_assemblies')
            ->whereIn('parent_product_id', $productIds)
            ->get();

        $compositeChildrenMap = [];
        foreach ($assemblies as $asm) {
            $compositeChildrenMap[$asm->parent_product_id][$asm->child_product_id] = $asm->quantity;
        }

        // ── Resolve pending-sourcing child products ────────────────────────────
        // A composite child is "pending sourcing" if the sale was saved with
        // sourcing_status = 'pending_parts'. We re-check by loading the line statuses.
        $pendingSourcingProducts = [];
        $linesByProduct = DB::table('transaction_lines')
            ->where('transaction_id', $sale->id)
            ->get()
            ->keyBy('product_id');

        foreach ($enrichedItems as $item) {
            $type = $products->get($item['product_id'])->type ?? 'standard';
            if ($type === 'composite' && isset($compositeChildrenMap[$item['product_id']])) {
                $line = $linesByProduct->get($item['product_id']);
                if ($line && $line->sourcing_status === 'pending_sourcing') {
                    foreach ($compositeChildrenMap[$item['product_id']] as $childId => $qtyPerParent) {
                        $pendingSourcingProducts[$childId] = true;
                    }
                }
            }
        }

        // ── Process each line in product_id order (deadlock prevention) ────────
        $sortedItems = $enrichedItems;
        usort($sortedItems, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

        foreach ($sortedItems as $item) {
            $fractionalRatio = $item['fractional_ratio'] ?? 1.0;
            $actualQty       = $item['quantity'] * $fractionalRatio;
            $productType     = $products->get($item['product_id'])->type ?? 'standard';

            // Retrieve the persisted line ID for serial tracking linkage
            $line   = $linesByProduct->get($item['product_id']);
            $lineId = $line?->id;

            $this->deductLineStock(
                item:                   $item,
                actualQty:              $actualQty,
                productType:            $productType,
                dto:                    $dto,
                lineId:                 $lineId,
                products:               $products,
                compositeChildrenMap:   $compositeChildrenMap,
                pendingSourcingProducts:$pendingSourcingProducts,
            );
        }
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Deduct stock for a single cart line.
     * Handles both standard and composite product types.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function deductLineStock(
        array  $item,
        float  $actualQty,
        string $productType,
        object $dto,
        ?int   $lineId,
        object $products,
        array  $compositeChildrenMap,
        array  $pendingSourcingProducts,
    ): void {
        if ($productType === 'composite' && isset($compositeChildrenMap[$item['product_id']])) {
            // Deduct from each child component stock individually
            foreach ($compositeChildrenMap[$item['product_id']] as $childId => $qtyPerParent) {
                if (isset($pendingSourcingProducts[$childId])) {
                    // Skip — this child will be sourced via procurement workflow
                    continue;
                }

                $totalChildQty = $actualQty * $qtyPerParent;

                $stock = DB::table('product_stocks')
                    ->where('business_id', $dto->businessId)
                    ->where('product_id', $childId)
                    ->where('location_id', $dto->locationId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || (float) $stock->qty_available < $totalChildQty) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'inventory' => ["Insufficient stock for kit component ID: {$childId}"],
                    ]);
                }

                DB::table('product_stocks')
                    ->where('id', $stock->id)
                    ->decrement('qty_available', $totalChildQty);
            }
        } else {
            // Standard product — deduct directly
            $stock = DB::table('product_stocks')
                ->where('business_id', $dto->businessId)
                ->where('product_id', $item['product_id'])
                ->where('location_id', $dto->locationId)
                ->lockForUpdate()
                ->first();

            if (! $stock || (float) $stock->qty_available < $actualQty) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inventory' => ['Strict POS Limit: Insufficient stock for product ID: ' . $item['product_id']],
                ]);
            }

            DB::table('product_stocks')
                ->where('id', $stock->id)
                ->decrement('qty_available', $actualQty);
        }

        // ── Serial / IMEI tracking ─────────────────────────────────────────────
        $productMeta    = $products->get($item['product_id']);
        $requiresSerial = isset($productMeta->enable_sr_no) && $productMeta->enable_sr_no == 1;

        if ($requiresSerial && empty($item['serial_numbers']) && empty($item['imei_numbers'])) {
            throw new \Exception(
                'Product ID ' . $item['product_id'] . ' requires a serial or IMEI number.'
            );
        }

        if (! empty($item['serial_numbers']) || ! empty($item['imei_numbers'])) {
            $this->processSerialTracking($item, $dto->businessId, $lineId);
        }
    }

    /**
     * Transfer serial/IMEI records from inventory ledger to transaction ledger.
     *
     * @throws \Exception
     */
    private function processSerialTracking(array $item, int $businessId, ?int $lineId): void
    {
        $serials     = $item['serial_numbers'] ?? [];
        $imeis       = $item['imei_numbers']   ?? [];
        $maxTracking = max(count($serials), count($imeis));

        if ($maxTracking !== (int) $item['quantity']) {
            throw new \Exception(
                'Serial count mismatch: got ' . $maxTracking . ', expected ' . $item['quantity']
            );
        }

        // Guard: reject already-transacted serials (double-sell prevention)
        $alreadyExists = DB::table('transaction_item_serials')
            ->where(function ($q) use ($serials, $imeis) {
                if (! empty($serials)) $q->orWhereIn('serial_number', $serials);
                if (! empty($imeis))   $q->orWhereIn('imei_number', $imeis);
            })
            ->exists();

        if ($alreadyExists) {
            throw new \Exception('FPM Security: Serial/IMEI already exists in active ledger.');
        }

        $available = DB::table('inventory_item_serials')
            ->where('business_id', $businessId)
            ->where('product_id', $item['product_id'])
            ->where('status', 'Available')
            ->where(function ($q) use ($serials, $imeis) {
                if (! empty($serials)) $q->orWhereIn('serial_number', $serials);
                if (! empty($imeis))   $q->orWhereIn('imei_number', $imeis);
            })
            ->lockForUpdate()
            ->get();

        if ($available->count() !== $maxTracking) {
            throw new \Exception(
                'One or more selected serial/IMEI numbers are not in Available stock.'
            );
        }

        $rows = [];
        for ($i = 0; $i < $maxTracking; $i++) {
            $rows[] = [
                'business_id'         => $businessId,
                'transaction_item_id' => $lineId,
                'serial_number'       => $serials[$i] ?? $imeis[$i],
                'imei_number'         => $imeis[$i]   ?? null,
                'created_at'          => Carbon::now(),
                'updated_at'          => Carbon::now(),
            ];
        }

        DB::table('transaction_item_serials')->insert($rows);

        DB::table('inventory_item_serials')
            ->whereIn('id', $available->pluck('id'))
            ->update([
                'status'                  => 'Sold',
                'transaction_sell_line_id'=> $lineId,
                'updated_at'              => Carbon::now(),
            ]);
    }
}
