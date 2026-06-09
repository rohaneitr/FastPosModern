<?php

namespace App\Modules\Finance\Services;

use Decimal\Decimal;

class CurrencyConverter
{
    /**
     * Reconcile a set of journal line entries, checking for micro-rounding drift.
     * If Debit != Credit, an adjusting 5400 line is pushed.
     * 
     * @param array &$lines Array of journal lines to be inserted.
     * @param int $businessId The tenant business ID.
     * @param int $transactionId The parent transaction ID.
     * @param string $historicalDate The transaction date.
     */
    public static function reconcileMicroRoundingVariance(array &$lines, int $businessId, int $transactionId, string $historicalDate): void
    {
        $sumDebits = new Decimal('0.0000');
        $sumCredits = new Decimal('0.0000');

        foreach ($lines as $line) {
            $sumDebits = $sumDebits->add(new Decimal((string)$line['debit_amount']));
            $sumCredits = $sumCredits->add(new Decimal((string)$line['credit_amount']));
        }

        $delta = $sumDebits->sub($sumCredits);

        if (!$delta->isZero()) {
            // Delta = Debits - Credits
            // If delta > 0, we have more debits than credits. We need to CREDIT 5400.
            // If delta < 0, we have more credits than debits. We need to DEBIT 5400.
            
            $debitAmount = '0.0000';
            $creditAmount = '0.0000';

            if ($delta->isPositive()) {
                $creditAmount = $delta->toString();
            } else {
                $debitAmount = $delta->abs()->toString();
            }

            $lines[] = [
                'transaction_id' => $transactionId,
                'business_id' => $businessId,
                'chart_of_account_code' => '5400',
                'debit_amount' => $debitAmount,
                'credit_amount' => $creditAmount,
                'currency_code' => null,
                'exchange_rate_used' => null,
                'created_at' => $historicalDate,
                'updated_at' => $historicalDate,
            ];
        }
    }
}
