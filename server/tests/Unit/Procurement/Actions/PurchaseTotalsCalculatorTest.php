<?php

namespace Tests\Unit\Procurement\Actions;

use App\Modules\Procurement\Actions\PurchaseTotalsCalculator;
use App\Modules\Procurement\Actions\PurchaseTotals;
use PHPUnit\Framework\TestCase;

/**
 * PurchaseTotalsCalculatorTest
 *
 * Pure unit tests — no database, no Laravel bootstrapping.
 * Tests the financial calculation engine that was previously copy-pasted
 * in both PurchaseController::store() and PurchaseController::update().
 *
 * WHAT WE ARE PROVING:
 *   1. Correct subtotal accumulation across multiple lines
 *   2. Fixed discount caps at subtotal (never goes negative)
 *   3. Percentage discount is calculated on subtotal (pre-tax)
 *   4. Tax is applied AFTER discount (correct accounting order)
 *   5. Shipping is added to grand total
 *   6. Payment status is correctly derived ('paid'/'partial'/'due')
 *   7. Amount due = grand_total - amount_paid (clamped at 0)
 *   8. Zero-value edge cases do not cause division errors
 *
 * @covers \App\Modules\Procurement\Actions\PurchaseTotalsCalculator
 */
class PurchaseTotalsCalculatorTest extends TestCase
{
    private PurchaseTotalsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PurchaseTotalsCalculator();
    }

    // ── 1. Basic subtotal ──────────────────────────────────────────────────────

    /** @test */
    public function it_calculates_correct_subtotal_for_single_line(): void
    {
        $totals = $this->calculator->calculate(
            lines: [['purchase_price' => 100.00, 'quantity' => 3]],
        );

        $this->assertEquals('300.0000', (string) $totals->subtotal);
    }

    /** @test */
    public function it_accumulates_multiple_line_totals(): void
    {
        $totals = $this->calculator->calculate(
            lines: [
                ['purchase_price' => 50.00, 'quantity' => 2],   // 100
                ['purchase_price' => 75.00, 'quantity' => 4],   // 300
                ['purchase_price' => 10.50, 'quantity' => 10],  // 105
            ],
        );

        // 100 + 300 + 105 = 505
        $this->assertEquals('505.0000', (string) $totals->subtotal);
    }

    // ── 2. Fixed discount ──────────────────────────────────────────────────────

    /** @test */
    public function it_applies_fixed_discount_correctly(): void
    {
        $totals = $this->calculator->calculate(
            lines:          [['purchase_price' => '200.00', 'quantity' => '1']],
            discountType:   'fixed',
            discountAmount: 50.00,
        );

        // discountValue = FinancialCalculator::of(50.00) — scale not guaranteed (float input)
        // Use numeric equality check instead of exact string match
        $this->assertTrue($totals->discountValue->isEqualTo('50'));
        $this->assertTrue($totals->afterDiscount->isEqualTo('150'));
    }

    /** @test */
    public function fixed_discount_is_capped_at_subtotal_and_never_goes_negative(): void
    {
        $totals = $this->calculator->calculate(
            lines:          [['purchase_price' => '100.00', 'quantity' => '1']],
            discountType:   'fixed',
            discountAmount: 999.00, // Way more than subtotal
        );

        // Discount capped at subtotal (100), not 999
        $this->assertTrue($totals->discountValue->isEqualTo('100'));
        // afterDiscount must be zero or positive — never negative
        $this->assertFalse($totals->afterDiscount->isNegative());
        $this->assertTrue($totals->afterDiscount->isZero());
        $this->assertTrue($totals->grandTotal->isZero());
    }

    // ── 3. Percentage discount ────────────────────────────────────────────────

    /** @test */
    public function it_applies_percentage_discount_on_subtotal(): void
    {
        $totals = $this->calculator->calculate(
            lines:          [['purchase_price' => 200.00, 'quantity' => 1]],
            discountType:   'percentage',
            discountAmount: 10.00, // 10%
        );

        // 10% of 200 = 20
        $this->assertEquals('20.0000', (string) $totals->discountValue);
        $this->assertEquals('180.0000', (string) $totals->afterDiscount);
    }

    // ── 4. Tax is applied AFTER discount (correct accounting order) ───────────

    /** @test */
    public function tax_is_calculated_on_after_discount_amount_not_subtotal(): void
    {
        // Subtotal: 200, Discount: 50 fixed, After discount: 150
        // Tax: 15% of 150 = 22.50 (NOT 15% of 200 = 30)
        $totals = $this->calculator->calculate(
            lines:          [['purchase_price' => 200.00, 'quantity' => 1]],
            taxRate:        15.0,
            discountType:   'fixed',
            discountAmount: 50.00,
        );

        $this->assertEquals('22.5000', (string) $totals->taxAmount);
        $this->assertEquals('172.5000', (string) $totals->grandTotal);
    }

    /** @test */
    public function it_applies_tax_without_discount(): void
    {
        $totals = $this->calculator->calculate(
            lines:   [['purchase_price' => 100.00, 'quantity' => 1]],
            taxRate: 5.0,
        );

        $this->assertEquals('5.0000', (string) $totals->taxAmount);
        $this->assertEquals('105.0000', (string) $totals->grandTotal);
    }

    // ── 5. Shipping ───────────────────────────────────────────────────────────

    /** @test */
    public function shipping_is_added_to_grand_total(): void
    {
        $totals = $this->calculator->calculate(
            lines:           [['purchase_price' => '100.00', 'quantity' => '1']],
            shippingCharges: 25.00,
        );

        $this->assertTrue($totals->shipping->isEqualTo('25'));
        $this->assertTrue($totals->grandTotal->isEqualTo('125'));
    }

    /** @test */
    public function grand_total_is_after_discount_plus_tax_plus_shipping(): void
    {
        // Subtotal: 500
        // Discount: 50 (fixed)
        // After discount: 450
        // Tax: 10% of 450 = 45
        // Shipping: 30
        // Grand total: 450 + 45 + 30 = 525
        $totals = $this->calculator->calculate(
            lines:           [['purchase_price' => 500.00, 'quantity' => 1]],
            taxRate:         10.0,
            discountType:    'fixed',
            discountAmount:  50.00,
            shippingCharges: 30.00,
        );

        $this->assertEquals('525.0000', (string) $totals->grandTotal);
    }

    // ── 6. Payment status derivation ─────────────────────────────────────────

    /** @test */
    public function payment_status_is_paid_when_amount_paid_equals_grand_total(): void
    {
        $totals = $this->calculator->calculate(
            lines:       [['purchase_price' => '200.00', 'quantity' => '1']],
            amountPaid:  200.00,
        );

        $this->assertEquals('paid', $totals->paymentStatus);
        $this->assertTrue($totals->amountDue->isZero());
    }

    /** @test */
    public function payment_status_is_paid_when_overpaid(): void
    {
        $totals = $this->calculator->calculate(
            lines:      [['purchase_price' => '100.00', 'quantity' => '1']],
            amountPaid: 150.00,
        );

        $this->assertEquals('paid', $totals->paymentStatus);
    }

    /** @test */
    public function payment_status_is_partial_when_partially_paid(): void
    {
        $totals = $this->calculator->calculate(
            lines:      [['purchase_price' => '200.00', 'quantity' => '1']],
            amountPaid: 50.00,
        );

        $this->assertEquals('partial', $totals->paymentStatus);
        $this->assertTrue($totals->amountDue->isEqualTo('150'));
    }

    /** @test */
    public function payment_status_is_due_when_nothing_paid(): void
    {
        $totals = $this->calculator->calculate(
            lines: [['purchase_price' => 500.00, 'quantity' => 2]],
        );

        $this->assertEquals('due', $totals->paymentStatus);
        $this->assertEquals('1000.0000', (string) $totals->amountDue);
    }

    // ── 7. toUpdateArray() serialization ─────────────────────────────────────

    /** @test */
    public function to_update_array_contains_all_required_purchase_columns(): void
    {
        $totals = $this->calculator->calculate(
            lines:         [['purchase_price' => 100.00, 'quantity' => 2]],
            taxRate:       10.0,
            discountType:  'fixed',
            discountAmount: 20.00,
        );

        $array = $totals->toUpdateArray('fixed');

        $this->assertArrayHasKey('total_before_tax', $array);
        $this->assertArrayHasKey('tax_amount', $array);
        $this->assertArrayHasKey('discount_amount', $array);
        $this->assertArrayHasKey('discount_type', $array);
        $this->assertArrayHasKey('shipping_charges', $array);
        $this->assertArrayHasKey('grand_total', $array);
        $this->assertArrayHasKey('amount_due', $array);
        $this->assertArrayHasKey('payment_status', $array);

        $this->assertEquals('fixed', $array['discount_type']);
        $this->assertEquals('200.0000', $array['total_before_tax']);
    }

    // ── 8. Edge cases ─────────────────────────────────────────────────────────

    /** @test */
    public function zero_tax_rate_produces_zero_tax_amount(): void
    {
        $totals = $this->calculator->calculate(
            lines:   [['purchase_price' => 500.00, 'quantity' => 1]],
            taxRate: 0.0,
        );

        $this->assertTrue($totals->taxAmount->isZero());
    }

    /** @test */
    public function no_discount_produces_zero_discount_value(): void
    {
        $totals = $this->calculator->calculate(
            lines: [['purchase_price' => 100.00, 'quantity' => 1]],
        );

        $this->assertTrue($totals->discountValue->isZero());
        $this->assertEquals('100.0000', (string) $totals->afterDiscount);
    }

    /** @test */
    public function fractional_quantities_are_handled_precisely(): void
    {
        // 2.5 kg at 40.00/kg = 100.0000
        $totals = $this->calculator->calculate(
            lines: [['purchase_price' => 40.00, 'quantity' => 2.5]],
        );

        $this->assertEquals('100.0000', (string) $totals->subtotal);
    }

    /** @test */
    public function returns_purchase_totals_value_object(): void
    {
        $result = $this->calculator->calculate(
            lines: [['purchase_price' => 10.00, 'quantity' => 1]],
        );

        $this->assertInstanceOf(PurchaseTotals::class, $result);
    }
}
