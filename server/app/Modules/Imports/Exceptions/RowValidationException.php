<?php

namespace App\Modules\Imports\Exceptions;

use Exception;

class RowValidationException extends Exception
{
    protected int $rowNumber;

    public function __construct(int $rowNumber, string $message)
    {
        parent::__construct($message);
        $this->rowNumber = $rowNumber;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }
}
