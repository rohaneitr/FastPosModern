<?php

namespace App\Modules\Procurement\Actions;

/**
 * PurchaseTotals — Immutable Value Object
 *
 * Returned by PurchaseTotalsCalculator::calculate().
 * Carries all financial totals for a purchase order as strongly-typed fields.
 * All monetary values use FinancialCalculator objects (BigDecimal-backed),
 * cast to string when persisted to the database.
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.4
 * @version 2026-06-12
 */
final class PurchaseTotals
{
    public function __construct(
        public readonly mixed  $subtotal,       // FinancialCalculator instance
        public readonly mixed  $discountValue,  // FinancialCalculator instance
        public readonly mixed  $afterDiscount,  // FinancialCalculator instance
        public readonly mixed  $taxAmount,      // FinancialCalculator instance
        public readonly mixed  $shipping,       // FinancialCalculator instance
        public readonly mixed  $grandTotal,     // FinancialCalculator instance
        public readonly mixed  $amountPaid,     // FinancialCalculator instance
        public readonly mixed  $amountDue,      // FinancialCalculator instance
        public readonly string $paymentStatus,  // 'paid' | 'partial' | 'due'
    ) {}

    /**
     * Serialize to an array for Purchase::update() calls.
     * Keys match the purchase table column names exactly.
     */
    public function toUpdateArray(string|null $discountType = null): array
    {
        return [
            'total_before_tax'  => (string) $this->subtotal,
            'tax_amount'        => (string) $this->taxAmount,
            'discount_amount'   => (string) $this->discountValue,
            'discount_type'     => $discountType,
            'shipping_charges'  => (string) $this->shipping,
            'grand_total'       => (string) $this->grandTotal,
            'amount_due'        => (string) $this->amountDue,
            'payment_status'    => $this->paymentStatus,
        ];
    }
}
