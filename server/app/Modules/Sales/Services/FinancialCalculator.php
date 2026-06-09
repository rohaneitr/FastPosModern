<?php

namespace App\Modules\Sales\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class FinancialCalculator
{
    private const SCALE = 4;
    private const DISPLAY_SCALE = 2;

    /**
     * Parse a value into a BigDecimal, ensuring zero if null/empty.
     */
    public static function of($value): BigDecimal
    {
        if (empty($value)) {
            return BigDecimal::zero();
        }
        
        return BigDecimal::of($value);
    }

    /**
     * Multiply unit price by quantity.
     */
    public static function calculateLineTotal($unitPrice, $quantity): BigDecimal
    {
        return self::of($unitPrice)
            ->multipliedBy(self::of($quantity))
            ->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    /**
     * Calculate tax amount for a given base amount.
     */
    public static function calculateTax($amount, $taxRate): BigDecimal
    {
        return self::of($amount)
            ->multipliedBy(self::of($taxRate))
            ->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    /**
     * Calculate percentage discount amount.
     */
    public static function calculatePercentageDiscount($amount, $percentage): BigDecimal
    {
        return self::of($amount)
            ->multipliedBy(self::of($percentage)->dividedBy(100, self::SCALE, RoundingMode::HALF_UP))
            ->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    /**
     * Subtract discount from amount, ensuring it doesn't go below zero.
     */
    public static function applyDiscount($amount, $discountAmount): BigDecimal
    {
        $result = self::of($amount)->minus(self::of($discountAmount));
        return $result->isNegative() ? BigDecimal::zero() : $result;
    }

    /**
     * Add two amounts.
     */
    public static function add($a, $b): BigDecimal
    {
        return self::of($a)->plus(self::of($b));
    }

    /**
     * Subtract amount b from a.
     */
    public static function subtract($a, $b): BigDecimal
    {
        return self::of($a)->minus(self::of($b));
    }
    
    /**
     * Round to display scale (2 decimals) as float for API responses.
     */
    public static function toFloat($amount): float
    {
        return self::of($amount)->toScale(self::DISPLAY_SCALE, RoundingMode::HALF_UP)->toFloat();
    }
    
    /**
     * Convert to exact DB string representation.
     */
    public static function toDbString($amount): string
    {
        return (string) self::of($amount)->toScale(self::SCALE, RoundingMode::HALF_UP);
    }
}
