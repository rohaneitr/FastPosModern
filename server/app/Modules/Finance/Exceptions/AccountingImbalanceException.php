<?php

namespace App\Modules\Finance\Exceptions;

use Exception;

class AccountingImbalanceException extends Exception
{
    public function __construct(string $message = 'Accounting entry is imbalanced. Debits must equal Credits.', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
