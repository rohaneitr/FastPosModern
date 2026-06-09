import Decimal from 'decimal.js';

// Configure Decimal to match Backend FinancialCalculator (SCALE=4, ROUND_HALF_UP)
Decimal.set({
  precision: 20, // Enough precision for internal calculation before rounding
  rounding: Decimal.ROUND_HALF_UP,
  toExpPos: 20, // Avoid scientific notation for large numbers
});

export const SCALE = 4;

export const formatDecimal = (value: Decimal.Value): string => {
  return new Decimal(value).toFixed(SCALE);
};

export { Decimal };
