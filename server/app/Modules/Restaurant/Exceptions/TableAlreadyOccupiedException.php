<?php

namespace App\Modules\Restaurant\Exceptions;

use Exception;

class TableAlreadyOccupiedException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage() ?: 'This table is already occupied.',
            'error_code' => 'TABLE_ALREADY_OCCUPIED'
        ], 409);
    }
}
