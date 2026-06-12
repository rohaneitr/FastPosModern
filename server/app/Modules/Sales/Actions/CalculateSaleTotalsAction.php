<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Sales\Services\FinancialCalculator;
use Illuminate\Support\Facades\DB;

/**
 * CalculateSaleTotalsAction — Single Responsibility: Price Calculation
 *
 * Extracted from TransactionController::checkout() lines ~129–168.
 * Responsibility: given cart items, tax rate, and discount, return
 * all financial totals as FinancialCalculator value objects.
 *
 * WHY AN ACTION CLASS?
 * The checkout calculation was duplicated across:
 *   1. checkout() method
 *   2. holdTransaction() method
 *   3. syncPush() method
 * A single Action class eliminates this duplication.
 *
 * ZERO TRUST:
 * - Frontend prices are IGNORED. This class re-fetches prices from the DB.
 * - Tax rate from client is used, but product price is always from `variations`.
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
final class CalculateSaleTotalsAction
{
    /**
     * Execute the price calculation.
     *
     * @param array  $items         Cart items (must include product_id, quantity, variation_id?)
     * @param float  $taxRate       Tax rate (0–100 scale, e.g. 15 for 15%)
     * @param string|null $discountType   'fixed' | 'percentage'
     * @param float  $discountAmount  Discount value
     *
     * @return SaleTotals
     */
    public function execute(
        array   $items,
        float   $taxRate,
        ?string $discountType,
        float   $discountAmount,
    ): SaleTotals {
        $subtotal = FinancialCalculator::of(0);
        $enrichedItems = [];

        foreach ($items as &$item) {
            // ZERO TRUST: Override any frontend-provided price with database price
            $variationQuery = DB::table('variations')
                ->where('product_id', $item['product_id']);
            if (!empty($item['variation_id'])) {
                $variationQuery->where('id', $item['variation_id']);
            }
            $variation = $variationQuery->first();

            if (!$variation) {
                throw new \RuntimeException(
                    'Invalid product variation for product ID: ' . $item['product_id']
                );
            }

            $item['price']     = (float) ($variation->sell_price_inc_tax ?? 0);
            $item['variation'] = $variation;

            $lineTotal = FinancialCalculator::calculateLineTotal($item['price'], $item['quantity']);
            $subtotal  = $subtotal->plus($lineTotal);

            $enrichedItems[] = $item;
        }
        unset($item);

        // Calculate discount
        $discountValue = FinancialCalculator::of(0);
        if (!empty($discountType) && $discountAmount > 0) {
            if ($discountType === 'percentage') {
                $discountValue = FinancialCalculator::calculatePercentageDiscount($subtotal, $discountAmount);
            } else {
                $discountValue = FinancialCalculator::of($discountAmount);
                if ($discountValue->isGreaterThan($subtotal)) {
                    $discountValue = clone $subtotal;
                }
            }
        }

        $afterDiscount = FinancialCalculator::applyDiscount($subtotal, $discountValue);
        $taxAmount     = FinancialCalculator::calculateTax($afterDiscount, $taxRate);
        $finalTotal    = $afterDiscount->plus($taxAmount);

        return new SaleTotals(
            subtotal:      $subtotal,
            discountValue: $discountValue,
            afterDiscount: $afterDiscount,
            taxAmount:     $taxAmount,
            finalTotal:    $finalTotal,
            enrichedItems: $enrichedItems,
        );
    }
}
