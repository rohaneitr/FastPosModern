<?php

namespace App\Modules\Manufacturing\Exceptions;

use Exception;

class InvalidStateException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'INVALID_PRODUCTION_STATE'
        ], 422);
    }
}
