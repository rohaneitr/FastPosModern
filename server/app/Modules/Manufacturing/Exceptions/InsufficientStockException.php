<?php

namespace App\Modules\Manufacturing\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'INSUFFICIENT_RAW_MATERIALS'
        ], 422);
    }
}
