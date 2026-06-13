<?php

namespace App\Http\Controllers;

use App\Core\Traits\ApiResponse;

/**
 * Base Controller
 *
 * All module controllers should extend this class.
 * Inherits the ApiResponse trait for standardized JSON envelopes.
 */
abstract class Controller
{
    use ApiResponse;
}
