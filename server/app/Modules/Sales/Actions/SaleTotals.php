<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Sales\Services\FinancialCalculator;

/**
 * SaleTotals — Value Object
 *
 * Holds the results of CalculateSaleTotalsAction.
 * All properties are readonly FinancialCalculator instances
 * or enriched item arrays.
 *
 * This is NOT a DTO — it holds FinancialCalculator objects,
 * which are immutable value objects themselves.
 *
 * @author  Antigravity AI Agent — Phase 3
 * @version 2026-06-12
 */
final class SaleTotals
{
    public function __construct(
        public readonly mixed  $subtotal,       // FinancialCalculator instance
        public readonly mixed  $discountValue,  // FinancialCalculator instance
        public readonly mixed  $afterDiscount,  // FinancialCalculator instance
        public readonly mixed  $taxAmount,      // FinancialCalculator instance
        public readonly mixed  $finalTotal,     // FinancialCalculator instance
        public readonly array  $enrichedItems,  // Items with DB-verified prices
    ) {}
}
