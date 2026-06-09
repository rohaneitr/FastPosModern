<?php

namespace App\Modules\HardwareBuilder\Exceptions;

use Exception;

class HardwareIncompatibilityException extends Exception
{
    protected $conflicts;

    public function __construct(array $conflicts, $message = "Hardware Incompatibility Detected")
    {
        parent::__construct($message);
        $this->conflicts = $conflicts;
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'HARDWARE_INCOMPATIBLE',
            'conflicts' => $this->conflicts
        ], 422);
    }
}
