<?php

namespace App\Modules\SerialCore\Exceptions;

use Exception;

class WarrantyExpiredException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'WARRANTY_EXPIRED'
        ], 403);
    }
}
