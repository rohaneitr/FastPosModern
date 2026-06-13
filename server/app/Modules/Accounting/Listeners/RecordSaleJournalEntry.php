<?php

namespace App\Modules\Accounting\Listeners;

use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Finance\Services\DoubleEntryEngine;
use App\Modules\Finance\Services\TenantAccountResolver;
use Carbon\Carbon;

/**
 * RecordSaleJournalEntry — Accounting Domain Listener
 *
 * Posts the double-entry journal for a completed sale.
 * Extracted from ProcessSaleService::postDoubleEntry().
 *
 * SYNCHRONOUS — NO ShouldQueue.
 *
 * WHY SYNCHRONOUS IS MANDATORY HERE:
 * The DoubleEntryEngine::recordEntry() throws AccountingImbalanceException
 * if debits ≠ credits. This exception MUST propagate up through the
 * DB::transaction() to trigger a full rollback of the sale. If this ran
 * on a queue, an accounting imbalance would cause a failed job but the
 * sale would already be committed — creating phantom revenue with no ledger.
 *
 * JOURNAL ENTRY STRUCTURE (Double-Entry):
 * ┌─────────────────────────────────────────────────────────┐
 * │ Debit:  Cash/Bank        ← amountPaid                   │
 * │ Debit:  Accounts Rec.    ← amountDue  (if credit sale)  │
 * │ Debit:  Discount Expense ← discountValue (if any)       │
 * │ Debit:  COGS             ← totalCogs (if > 0)           │
 * │ Credit: Sales Revenue    ← subtotal                      │
 * │ Credit: Tax Payable      ← taxAmount (if > 0)           │
 * │ Credit: Inventory Asset  ← totalCogs (if > 0)           │
 * └─────────────────────────────────────────────────────────┘
 *
 * RESPONSIBILITY BOUNDARY:
 * This listener ONLY touches `journal_entries` and `journal_lines`.
 * It resolves chart-of-account IDs via TenantAccountResolver and
 * delegates the actual write to DoubleEntryEngine.
 *
 * @version Phase 5 — Domain Event Decoupling
 */
class RecordSaleJournalEntry
{
    public function __construct(
        private readonly DoubleEntryEngine $doubleEntry,
    ) {}

    /**
     * Handle the SaleCompleted event.
     *
     * @throws \App\Modules\Finance\Exceptions\AccountingImbalanceException
     */
    public function handle(SaleCompleted $event): void
    {
        // Journal entries only apply to finalized (posted) invoices
        if (! $event->dto->isPosting) {
            return;
        }

        $businessId = $event->dto->businessId;
        $userId     = $event->dto->userId;
        $invoiceNo  = $event->invoiceNo;
        $txId       = $event->sale->id;
        $totals     = $event->totals;
        $amountPaid = $event->amountPaid;
        $amountDue  = $event->amountDue;
        $totalCogs  = $event->totalCogs;

        // ── Resolve Chart of Account IDs for this tenant ──────────────────────
        $cash     = TenantAccountResolver::resolve($businessId, TenantAccountResolver::CASH);
        $ar       = TenantAccountResolver::resolve($businessId, TenantAccountResolver::AR);
        $sales    = TenantAccountResolver::resolve($businessId, TenantAccountResolver::SALES);
        $tax      = TenantAccountResolver::resolve($businessId, TenantAccountResolver::TAX_PAYABLE);
        $discount = TenantAccountResolver::resolve($businessId, TenantAccountResolver::DISCOUNT);
        $cogs     = TenantAccountResolver::resolve($businessId, TenantAccountResolver::COGS);
        $inv      = TenantAccountResolver::resolve($businessId, TenantAccountResolver::INVENTORY);

        // ── Build balanced debit/credit arrays ────────────────────────────────
        $debits  = [];
        $credits = [];

        // Revenue side (credits)
        $credits[] = ['chart_of_account_id' => $sales, 'amount' => (string) $totals->subtotal];

        if ($totals->taxAmount->isGreaterThan(0)) {
            $credits[] = ['chart_of_account_id' => $tax, 'amount' => (string) $totals->taxAmount];
        }

        if ($totalCogs->isGreaterThan(0)) {
            // Inventory asset reduction (credit = asset decreases)
            $credits[] = ['chart_of_account_id' => $inv, 'amount' => (string) $totalCogs];
        }

        // Payment side (debits)
        if ($totals->discountValue->isGreaterThan(0)) {
            $debits[] = ['chart_of_account_id' => $discount, 'amount' => (string) $totals->discountValue];
        }

        if ($amountPaid->isGreaterThan(0)) {
            $debits[] = ['chart_of_account_id' => $cash, 'amount' => (string) $amountPaid];
        }

        if ($amountDue->isGreaterThan(0)) {
            // Credit sale — outstanding balance goes to Accounts Receivable
            $debits[] = ['chart_of_account_id' => $ar, 'amount' => (string) $amountDue];
        }

        if ($totalCogs->isGreaterThan(0)) {
            // COGS expense recognition (debit = expense increases)
            $debits[] = ['chart_of_account_id' => $cogs, 'amount' => (string) $totalCogs];
        }

        // ── Post the balanced entry via DoubleEntryEngine ─────────────────────
        // DoubleEntryEngine verifies Σdebits === Σcredits and throws
        // AccountingImbalanceException if they don't match — which rolls back
        // the entire sale transaction.
        $this->doubleEntry->recordEntry(
            businessId:     $businessId,
            referenceNumber:$invoiceNo,
            date:           Carbon::now()->toDateString(),
            narration:      "Sale #{$invoiceNo}",
            debits:         $debits,
            credits:        $credits,
            referenceId:    $txId,
            referenceType:  'transaction',
            userId:         $userId,
        );
    }
}
