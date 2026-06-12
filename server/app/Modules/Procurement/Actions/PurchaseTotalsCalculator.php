<?php

namespace App\Modules\Procurement\Actions;

use App\Modules\Sales\Services\FinancialCalculator;

/**
 * PurchaseTotalsCalculator — Value Object + Calculator
 *
 * Extracted from PurchaseController::store() (lines 193–258)
 * and PurchaseController::update() (lines 379–445).
 *
 * DUPLICATION ELIMINATED:
 * This exact block was copy-pasted in both store() and update():
 *   subtotal → discount (fixed or %) → after_discount → tax → shipping → grand_total → payment_status
 *
 * Single source of truth: if the formula changes, it changes in ONE place.
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.4
 * @version 2026-06-12
 */
final class PurchaseTotalsCalculator
{
    /**
     * Calculate all financial totals for a purchase order.
     *
     * @param array $lines   Each item must have 'purchase_price' and 'quantity'
     * @param float $taxRate Tax rate (0–100 scale, e.g. 15 for 15%)
     * @param string|null $discountType  'fixed' | 'percentage' | null
     * @param float $discountAmount
     * @param float $shippingCharges
     * @param float $amountPaid
     *
     * @return PurchaseTotals
     */
    public function calculate(
        array   $lines,
        float   $taxRate        = 0,
        ?string $discountType   = null,
        float   $discountAmount = 0,
        float   $shippingCharges = 0,
        float   $amountPaid     = 0,
    ): PurchaseTotals {
        // ── 1. Sum line subtotals ─────────────────────────────────────────────
        $subtotal = FinancialCalculator::of(0);
        foreach ($lines as $line) {
            $lineSub  = FinancialCalculator::calculateLineTotal($line['purchase_price'], $line['quantity']);
            $subtotal = $subtotal->plus($lineSub);
        }

        // ── 2. Calculate discount ─────────────────────────────────────────────
        $discValue = FinancialCalculator::of(0);
        if (!empty($discountType) && $discountAmount > 0) {
            if ($discountType === 'percentage') {
                $discValue = FinancialCalculator::calculatePercentageDiscount($subtotal, $discountAmount);
            } else {
                $discValue = FinancialCalculator::of($discountAmount);
                if ($discValue->isGreaterThan($subtotal)) {
                    $discValue = clone $subtotal;
                }
            }
        } elseif ($discountAmount > 0) {
            $discValue = FinancialCalculator::of($discountAmount);
            if ($discValue->isGreaterThan($subtotal)) {
                $discValue = clone $subtotal;
            }
        }

        // ── 3. After discount, tax, shipping ─────────────────────────────────
        $afterDiscount = FinancialCalculator::applyDiscount($subtotal, $discValue);
        $taxAmount     = FinancialCalculator::calculateTax($afterDiscount, $taxRate);
        $shipping      = FinancialCalculator::of($shippingCharges);
        $grandTotal    = $afterDiscount->plus($taxAmount)->plus($shipping);

        // ── 4. Payment status ─────────────────────────────────────────────────
        $paid          = FinancialCalculator::of($amountPaid);
        $amountDue     = FinancialCalculator::applyDiscount($grandTotal, $paid);
        $paymentStatus = $paid->isGreaterThanOrEqualTo($grandTotal)
            ? 'paid'
            : ($paid->isGreaterThan(0) ? 'partial' : 'due');

        return new PurchaseTotals(
            subtotal:      $subtotal,
            discountValue: $discValue,
            afterDiscount: $afterDiscount,
            taxAmount:     $taxAmount,
            shipping:      $shipping,
            grandTotal:    $grandTotal,
            amountPaid:    $paid,
            amountDue:     $amountDue,
            paymentStatus: $paymentStatus,
        );
    }
}
