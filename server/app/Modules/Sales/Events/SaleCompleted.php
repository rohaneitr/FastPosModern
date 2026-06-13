<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\DataTransferObjects\SaleCheckoutDTO;
use App\Modules\Sales\Actions\SaleTotals;
use App\Modules\Sales\Services\FinancialCalculator;

/**
 * SaleCompleted — Domain Event
 *
 * Fired synchronously inside DB::transaction() AFTER:
 *   - The `transactions` header row is saved
 *   - All `transaction_lines` are saved
 *   - The `transaction_payments` record is saved
 *
 * SYNCHRONOUS CONTRACT (CRITICAL):
 * Listeners MUST NOT implement ShouldQueue. Because this event is
 * dispatched inside a DB::transaction(), any exception thrown by a
 * listener (e.g. "Insufficient stock", "Accounting imbalance")
 * automatically propagates up and rolls back the ENTIRE transaction.
 * This is the mechanism that ensures atomic consistency across
 * Inventory, CRM, and Accounting domains.
 *
 * What listeners receive via $event->XXX:
 *   $sale          — The hydrated Sale Eloquent model (id, business_id, etc.)
 *   $dto           — The original immutable checkout payload (business rules context)
 *   $totals        — The computed financial totals (subtotal, tax, discount, final)
 *   $totalCogs     — Aggregated COGS from FIFO batch consumption (FinancialCalculator)
 *   $amountPaid    — Tendered amount (FinancialCalculator)
 *   $amountDue     — Remaining balance due (FinancialCalculator)
 *   $invoiceNo     — The generated invoice reference string
 *
 * @version Phase 5 — Domain Event Decoupling
 */
final class SaleCompleted
{
    public function __construct(
        /**
         * The persisted Sale (Transaction) Eloquent model.
         * Listeners can call $sale->id, $sale->business_id, $sale->lines(), etc.
         */
        public readonly Sale $sale,

        /**
         * The original immutable checkout payload.
         * Provides: businessId, userId, locationId, contactId,
         *           paymentMethod, isPosting, items, taxRate, etc.
         */
        public readonly SaleCheckoutDTO $dto,

        /**
         * Computed financial totals (FinancialCalculator value objects).
         * Provides: subtotal, discountValue, afterDiscount, taxAmount, finalTotal, enrichedItems.
         */
        public readonly SaleTotals $totals,

        /**
         * Aggregated COGS from FIFO batch consumption across all lines.
         * This is a FinancialCalculator instance. Listeners check ->isGreaterThan(0).
         */
        public readonly mixed $totalCogs,

        /**
         * Amount tendered by the customer (FinancialCalculator instance).
         */
        public readonly mixed $amountPaid,

        /**
         * Remaining balance still owed after payment (FinancialCalculator instance).
         * = finalTotal - amountPaid. Zero for fully paid sales.
         */
        public readonly mixed $amountDue,

        /**
         * The invoice/reference number generated for this transaction.
         * Format: 'INV-{timestamp}-{rand}' or 'QT-{timestamp}-{rand}'
         */
        public readonly string $invoiceNo,
    ) {}
}
