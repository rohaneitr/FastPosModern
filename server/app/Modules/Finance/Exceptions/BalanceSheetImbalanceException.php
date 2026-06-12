<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

class BalanceSheetImbalanceException extends RuntimeException
{
    public function __construct(string $assets, string $liabilitiesAndEquity)
    {
        $message = "CRITICAL LEDGER INTEGRITY FAILURE: Balance Sheet Imbalance Detected. " .
                   "Total Assets ({$assets}) do not equal Total Liabilities & Equity ({$liabilitiesAndEquity}).";
        parent::__construct($message);
    }
}
