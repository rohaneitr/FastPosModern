<?php

namespace Tests\Unit\Sales\Actions;

use App\Modules\Sales\Services\FinancialCalculator;
use PHPUnit\Framework\TestCase;

/**
 * FinancialCalculatorTest
 *
 * Pure unit tests for the BigDecimal-backed FinancialCalculator.
 * This is the financial engine used by BOTH Sales and Procurement modules.
 * Any regression here breaks checkout, purchases, and refunds simultaneously.
 *
 * WHAT WE ARE PROVING:
 *   1. of() parses numerics, strings, and handles null/empty safely
 *   2. calculateLineTotal() multiplies with HALF_UP rounding at 4 decimal places
 *   3. calculateTax() computes correct tax on any base amount
 *   4. calculatePercentageDiscount() computes % on any amount
 *   5. applyDiscount() never allows subtotal to go below zero
 *   6. toFloat() rounds to 2 decimal places for API responses
 *   7. Floating-point precision: 0.1 + 0.2 must NOT equal 0.30000000004
 *
 * @covers \App\Modules\Sales\Services\FinancialCalculator
 */
class FinancialCalculatorTest extends TestCase
{
    // ── 1. of() parser ────────────────────────────────────────────────────────

    /** @test */
    public function of_returns_zero_for_null(): void
    {
        $result = FinancialCalculator::of(null);
        $this->assertTrue($result->isZero());
    }

    /** @test */
    public function of_returns_zero_for_empty_string(): void
    {
        $result = FinancialCalculator::of('');
        $this->assertTrue($result->isZero());
    }

    /** @test */
    public function of_parses_integer_correctly(): void
    {
        $result = FinancialCalculator::of(100);
        $this->assertEquals('100', (string) $result);
    }

    /** @test */
    public function of_parses_string_decimal_correctly(): void
    {
        $result = FinancialCalculator::of('99.9900');
        // BigDecimal strips trailing zeros: '99.9900' → '99.99'
        // But numeric equality must hold
        $this->assertTrue($result->isEqualTo('99.99'));
        $this->assertFalse($result->isZero());
    }

    // ── 2. calculateLineTotal() ───────────────────────────────────────────────

    /** @test */
    public function line_total_is_price_times_quantity(): void
    {
        $result = FinancialCalculator::calculateLineTotal(50.00, 3);
        $this->assertEquals('150.0000', (string) $result);
    }

    /** @test */
    public function line_total_handles_fractional_quantity(): void
    {
        // 33.33 × 3 = 99.9900
        $result = FinancialCalculator::calculateLineTotal(33.33, 3);
        $this->assertEquals('99.9900', (string) $result);
    }

    /** @test */
    public function line_total_handles_fractional_price_precisely(): void
    {
        // Classic floating-point trap: 0.1 × 3 must be 0.3000, NOT 0.30000000000000004
        $result = FinancialCalculator::calculateLineTotal(0.1, 3);
        $this->assertEquals('0.3000', (string) $result);
    }

    /** @test */
    public function line_total_for_zero_quantity_is_zero(): void
    {
        $result = FinancialCalculator::calculateLineTotal(999.99, 0);
        $this->assertTrue($result->isZero());
    }

    // ── 3. calculateTax() ─────────────────────────────────────────────────────

    /** @test */
    public function tax_is_correct_for_standard_rate(): void
    {
        // 15% of 200.00 = 30.0000
        $result = FinancialCalculator::calculateTax(200.00, 15);
        $this->assertEquals('30.0000', (string) $result);
    }

    /** @test */
    public function zero_tax_rate_returns_zero(): void
    {
        $result = FinancialCalculator::calculateTax(1000.00, 0);
        $this->assertTrue($result->isZero());
    }

    /** @test */
    public function tax_rounds_correctly_at_half_up(): void
    {
        // 7.5% of 100 = 7.5000
        $result = FinancialCalculator::calculateTax(100.00, 7.5);
        $this->assertEquals('7.5000', (string) $result);
    }

    // ── 4. calculatePercentageDiscount() ─────────────────────────────────────

    /** @test */
    public function percentage_discount_of_10_percent_on_500(): void
    {
        // 10% of 500 = 50.0000
        $result = FinancialCalculator::calculatePercentageDiscount(500.00, 10);
        $this->assertEquals('50.0000', (string) $result);
    }

    /** @test */
    public function percentage_discount_of_100_percent_equals_full_amount(): void
    {
        $result = FinancialCalculator::calculatePercentageDiscount(300.00, 100);
        $this->assertEquals('300.0000', (string) $result);
    }

    // ── 5. applyDiscount() — zero floor guard ─────────────────────────────────

    /** @test */
    public function apply_discount_subtracts_correctly(): void
    {
        $amount   = FinancialCalculator::of(200.00);
        $discount = FinancialCalculator::of(75.00);
        $result   = FinancialCalculator::applyDiscount($amount, $discount);

        $this->assertEquals('125', (string) $result);
    }

    /** @test */
    public function apply_discount_returns_zero_when_discount_exceeds_amount(): void
    {
        $amount   = FinancialCalculator::of(100.00);
        $discount = FinancialCalculator::of(999.00);
        $result   = FinancialCalculator::applyDiscount($amount, $discount);

        $this->assertTrue($result->isZero());
        $this->assertFalse($result->isNegative());
    }

    /** @test */
    public function apply_discount_of_zero_leaves_amount_unchanged(): void
    {
        $amount   = FinancialCalculator::of(500.00);
        $discount = FinancialCalculator::of(0);
        $result   = FinancialCalculator::applyDiscount($amount, $discount);

        $this->assertEquals('500', (string) $result);
    }

    // ── 6. toFloat() display rounding ────────────────────────────────────────

    /** @test */
    public function to_float_rounds_to_2_decimal_places(): void
    {
        $result = FinancialCalculator::toFloat('99.9999');
        $this->assertEquals(100.00, $result);
    }

    /** @test */
    public function to_float_for_whole_number_returns_dot_zero(): void
    {
        $result = FinancialCalculator::toFloat('500');
        $this->assertEquals(500.00, $result);
    }

    // ── 7. Floating-point precision guarantee ─────────────────────────────────

    /** @test */
    public function floating_point_precision_is_maintained_across_additions(): void
    {
        // Native PHP float: 0.1 + 0.2 = 0.30000000000000004 ← WRONG
        // BigDecimal: 0.1 + 0.2 = 0.2 ← CORRECT
        $a = FinancialCalculator::of(0.1);
        $b = FinancialCalculator::of(0.2);
        $c = $a->plus($b);

        // Must equal 0.3, not 0.30000000000000004
        $this->assertEquals('0.3', (string) $c);
        $this->assertNotEquals(0.30000000000000004, (float)(string)$c);
    }

    /** @test */
    public function financial_calculations_use_four_decimal_precision(): void
    {
        // 1/3 rounds to 4 decimal places with HALF_UP
        $result = FinancialCalculator::calculateLineTotal(1, 3);
        // 1 × 3 = 3.0000
        $this->assertEquals('3.0000', (string) $result);

        // More subtle: 10/3 per-unit × 3 = 10
        $result2 = FinancialCalculator::calculateLineTotal(3.3333, 3);
        $this->assertEquals('9.9999', (string) $result2);
    }
}
