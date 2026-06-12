<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Procurement\Models\Purchase;
use App\Modules\Procurement\Actions\PurchaseTotalsCalculator;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Models\InventoryLayer;
use App\Modules\Finance\Services\DoubleEntryEngine;
use App\Modules\Finance\Services\TenantAccountResolver;
use App\Modules\Security\Services\ForensicAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ProcessPurchaseService
 *
 * Extracted from PurchaseController::store() (lines 174–340) and
 * PurchaseController::update() (lines 351–535).
 *
 * This service encapsulates the full purchase order lifecycle:
 *   1. Create/update Purchase header record
 *   2. Process each line item (PurchaseLine + StockLedger + FIFO InventoryLayer)
 *   3. Calculate financial totals via PurchaseTotalsCalculator (single source of truth)
 *   4. Record supplier ledger entry
 *   5. Record double-entry journal (Dr Inventory / Cr Cash + AP)
 *   6. Forensic audit snapshot
 *
 * ZERO TRUST:
 *   - business_id is injected from the authenticated user, never from the request payload
 *   - All stock and ledger inserts carry explicit business_id
 *   - Entire operation is wrapped in a DB::transaction()
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.4
 * @version 2026-06-12
 */
class ProcessPurchaseService
{
    public function __construct(
        private readonly PurchaseTotalsCalculator $totalsCalculator,
        private readonly DoubleEntryEngine         $ledger,
        private readonly ForensicAuditService      $auditService,
    ) {}

    /**
     * Create a new Purchase Order with full FIFO + ledger integration.
     *
     * @param int   $businessId  Authenticated user's business ID
     * @param int   $actorId     Authenticated user's ID
     * @param array $data        Already-validated data from StorePurchaseRequest
     *
     * @return Purchase  Loaded with 'contact' and 'lines.product'
     */
    public function create(int $businessId, int $actorId, array $data): Purchase
    {
        return DB::transaction(function () use ($businessId, $actorId, $data) {
            $referenceNo = $data['reference_no'] ?? 'PO-' . strtoupper(Str::random(6));

            // ── 1. Create Purchase header ─────────────────────────────────────
            $purchase = Purchase::create([
                'business_id'  => $businessId,
                'contact_id'   => $data['contact_id'],
                'reference_no' => $referenceNo,
                'purchase_date' => $data['purchase_date'],
                'status'       => $data['status'],
                'note'         => $data['note'] ?? null,
                'grand_total'  => 0,
            ]);

            // ── 2. Process lines ──────────────────────────────────────────────
            $this->processLines($purchase, $data['lines'], $businessId, $actorId);

            // ── 3. Calculate totals & update header ───────────────────────────
            $totals = $this->totalsCalculator->calculate(
                $data['lines'],
                (float) ($data['tax_rate'] ?? 0),
                $data['discount_type'] ?? null,
                (float) ($data['discount_amount'] ?? 0),
                (float) ($data['shipping_charges'] ?? 0),
                (float) ($data['amount_paid'] ?? 0),
            );

            $purchase->update($totals->toUpdateArray($data['discount_type'] ?? null));

            // ── 4. Supplier ledger entry ──────────────────────────────────────
            $this->recordSupplierLedger(
                $businessId,
                $data['contact_id'],
                $purchase->id,
                (string) $totals->grandTotal,
                $referenceNo,
                'insert',
            );

            // ── 5. Double-entry journal ───────────────────────────────────────
            $journalEntry = $this->recordJournalEntry(
                $businessId,
                $referenceNo,
                $data['purchase_date'],
                'Purchase ' . $referenceNo,
                $totals,
                $purchase->id,
                $actorId,
            );

            // ── 6. Forensic audit snapshot ────────────────────────────────────
            $this->auditService->snapshot(
                'journal_entries', $journalEntry->id, 'created',
                'purchase_ledger_created', null, $journalEntry->toArray(),
                'api/v1/purchases',
            );

            return $purchase->load(['contact', 'lines.product']);
        });
    }

    /**
     * Update an existing Purchase Order with full stock revert + FIFO re-apply.
     *
     * @param int      $businessId
     * @param int      $actorId
     * @param Purchase $purchase  The model being updated (route model binding)
     * @param array    $data      Already-validated data from StorePurchaseRequest
     *
     * @return Purchase  Loaded with 'contact' and 'lines.product'
     */
    public function update(int $businessId, int $actorId, Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($businessId, $actorId, $purchase, $data) {
            $oldStatus = $purchase->status;
            $newStatus = $data['status'];

            // ── 1. Revert old stock ledger entries if previously received ─────
            if ($oldStatus === 'received') {
                StockLedger::where('transaction_type', 'purchase')
                    ->where('transaction_id', $purchase->id)
                    ->delete();
            }

            // ── 2. Delete old lines ───────────────────────────────────────────
            $purchase->lines()->delete();

            // ── 3. Update purchase header ─────────────────────────────────────
            $purchase->update([
                'contact_id'    => $data['contact_id'],
                'reference_no'  => $data['reference_no'] ?? $purchase->reference_no,
                'purchase_date' => $data['purchase_date'],
                'status'        => $newStatus,
                'note'          => $data['note'] ?? $purchase->note,
                'grand_total'   => 0,
            ]);

            // ── 4. Process new lines ──────────────────────────────────────────
            $this->processLines($purchase, $data['lines'], $businessId, $actorId);

            // ── 5. Calculate totals & update header ───────────────────────────
            $totals = $this->totalsCalculator->calculate(
                $data['lines'],
                (float) ($data['tax_rate'] ?? 0),
                $data['discount_type'] ?? null,
                (float) ($data['discount_amount'] ?? 0),
                (float) ($data['shipping_charges'] ?? 0),
                (float) ($data['amount_paid'] ?? 0),
            );

            $purchase->update($totals->toUpdateArray($data['discount_type'] ?? null));

            // ── 6. Update supplier ledger ─────────────────────────────────────
            $this->recordSupplierLedger(
                $businessId,
                $data['contact_id'],
                $purchase->id,
                (string) $totals->grandTotal,
                $purchase->reference_no,
                'update',
            );

            // ── 7. Replace double-entry journal ───────────────────────────────
            $oldEntry      = \App\Models\JournalEntry::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->first();
            $oldEntryArray = $oldEntry?->toArray();

            if ($oldEntry) {
                \App\Models\JournalLine::where('journal_entry_id', $oldEntry->id)->delete();
                $oldEntry->delete();
            }

            $journalEntry = $this->recordJournalEntry(
                $businessId,
                $purchase->reference_no,
                $data['purchase_date'],
                'Purchase Update ' . $purchase->reference_no,
                $totals,
                $purchase->id,
                $actorId,
            );

            // ── 8. Forensic audit snapshot ────────────────────────────────────
            $this->auditService->snapshot(
                'journal_entries', $journalEntry->id, 'updated',
                'purchase_ledger_updated', $oldEntryArray, $journalEntry->toArray(),
                'api/v1/purchases/' . $purchase->id,
            );

            return $purchase->fresh(['contact', 'lines.product']);
        });
    }

    // ── Private Pipeline Steps ────────────────────────────────────────────────

    /**
     * Process each purchase line:
     *   - Create PurchaseLine record
     *   - Create StockLedger entry if status = 'received'
     *   - Reconcile negative layers (True-Up)
     *   - Create FIFO InventoryLayer for remaining qty
     */
    private function processLines(Purchase $purchase, array $lines, int $businessId, int $actorId): void
    {
        foreach ($lines as $lineData) {
            $lineSub = \App\Modules\Sales\Services\FinancialCalculator::calculateLineTotal(
                $lineData['purchase_price'],
                $lineData['quantity']
            );

            $purchaseLine = $purchase->lines()->create([
                'business_id'   => $businessId,
                'product_id'    => $lineData['product_id'],
                'quantity'      => $lineData['quantity'],
                'purchase_price' => $lineData['purchase_price'],
                'item_tax'      => '0.0000',
                'sub_total'     => (string) $lineSub,
            ]);

            if ($purchase->status === 'received') {
                StockLedger::create([
                    'business_id'      => $businessId,
                    'product_id'       => $lineData['product_id'],
                    'transaction_type' => 'purchase',
                    'transaction_id'   => $purchase->id,
                    'quantity'         => $lineData['quantity'],
                ]);

                // True-Up: Reconcile any existing negative inventory layers
                $reconcileAction = app(\App\Modules\Inventory\Actions\ReconcileNegativeLayersAction::class);
                $remainingQty    = $reconcileAction->execute(
                    $businessId,
                    $lineData['product_id'],
                    $lineData['quantity'],
                    $lineData['purchase_price'],
                    $purchase->id,
                    $actorId,
                );

                // FIFO: Create cost layer for remaining qty after negative reconciliation
                if (\Brick\Math\BigDecimal::of($remainingQty)->isGreaterThan(0)) {
                    InventoryLayer::create([
                        'business_id'     => $businessId,
                        'product_id'      => $lineData['product_id'],
                        'purchase_line_id' => $purchaseLine->id,
                        'original_qty'    => $remainingQty,
                        'remaining_qty'   => $remainingQty,
                        'unit_cost'       => $lineData['purchase_price'],
                    ]);
                }
            }
        }
    }

    /**
     * Write or update the supplier ledger entry.
     */
    private function recordSupplierLedger(
        int    $businessId,
        int    $contactId,
        int    $purchaseId,
        string $grandTotal,
        string $referenceNo,
        string $mode, // 'insert' | 'update'
    ): void {
        if ($mode === 'insert') {
            DB::table('supplier_ledgers')->insert([
                'business_id' => $businessId,
                'contact_id'  => $contactId,
                'purchase_id' => $purchaseId,
                'amount'      => $grandTotal,
                'type'        => 'credit',
                'description' => 'Purchase ' . $referenceNo,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('supplier_ledgers')
                ->where('purchase_id', $purchaseId)
                ->update([
                    'contact_id' => $contactId,
                    'amount'     => $grandTotal,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Build and record the double-entry journal for a purchase.
     * Dr Inventory / Cr Cash (paid) + Cr Accounts Payable (due)
     */
    private function recordJournalEntry(
        int    $businessId,
        string $referenceNo,
        string $date,
        string $description,
        \App\Modules\Procurement\Actions\PurchaseTotals $totals,
        int    $purchaseId,
        int    $actorId,
    ): \App\Models\JournalEntry {
        $inventoryAccountId = TenantAccountResolver::resolve($businessId, TenantAccountResolver::INVENTORY);
        $cashAccountId      = TenantAccountResolver::resolve($businessId, TenantAccountResolver::CASH);
        $apAccountId        = TenantAccountResolver::resolve($businessId, TenantAccountResolver::AP);

        $debits  = [];
        $credits = [];

        if ($totals->grandTotal->isGreaterThan(0)) {
            $debits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $totals->grandTotal];
        }

        if ($totals->amountPaid->isGreaterThan(0)) {
            $appliedPaid = $totals->amountPaid->isGreaterThan($totals->grandTotal)
                ? clone $totals->grandTotal
                : clone $totals->amountPaid;
            $credits[] = ['chart_of_account_id' => $cashAccountId, 'amount' => (string) $appliedPaid];
        }

        if ($totals->amountDue->isGreaterThan(0)) {
            $credits[] = ['chart_of_account_id' => $apAccountId, 'amount' => (string) $totals->amountDue];
        }

        return $this->ledger->recordEntry(
            $businessId,
            $referenceNo,
            $date,
            $description,
            $debits,
            $credits,
            $purchaseId,
            'purchase',
            $actorId,
        );
    }
}
