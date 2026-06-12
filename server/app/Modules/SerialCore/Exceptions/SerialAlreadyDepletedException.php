<?php

namespace App\Modules\SerialCore\Exceptions;

use Exception;

class SerialAlreadyDepletedException extends Exception
{
    protected $invalidSerials;

    public function __construct(array $invalidSerials, $message = "One or more scanned serials are invalid or already sold.")
    {
        parent::__construct($message);
        $this->invalidSerials = $invalidSerials;
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'SERIAL_DEPLETED',
            'invalid_serials' => $this->invalidSerials
        ], 422);
    }
}
