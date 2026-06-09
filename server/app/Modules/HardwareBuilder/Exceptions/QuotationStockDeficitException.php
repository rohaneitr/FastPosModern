<?php

namespace App\Modules\HardwareBuilder\Exceptions;

use Exception;

class QuotationStockDeficitException extends Exception
{
    protected $deficitItems;

    public function __construct(array $deficitItems, $message = "Insufficient Stock for Quotation Conversion")
    {
        parent::__construct($message);
        $this->deficitItems = $deficitItems;
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'QUOTATION_STOCK_DEFICIT',
            'deficits' => $this->deficitItems
        ], 409); // 409 Conflict instead of 422 for stock issues
    }
}
