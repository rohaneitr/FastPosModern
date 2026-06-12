<?php

namespace App\Modules\Pharmacy\Exceptions;

use Exception;

class ExpiredStockException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'PHARMACY_EXPIRED_STOCK'
        ], 422);
    }
}
