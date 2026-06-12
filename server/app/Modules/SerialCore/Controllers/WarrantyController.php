<?php

namespace App\Modules\SerialCore\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\SerialCore\Services\WarrantyManager;

class WarrantyController extends Controller
{
    protected $warrantyManager;

    public function __construct(WarrantyManager $warrantyManager)
    {
        $this->warrantyManager = $warrantyManager;
    }

    public function verify($serialNumber, Request $request)
    {
        $businessId = $request->user()->business_id ?? 1; // Assuming middleware sets this
        
        try {
            $result = $this->warrantyManager->verifyWarranty($serialNumber, $businessId);
            return response()->json($result);
        } catch (\App\Modules\SerialCore\Exceptions\WarrantyExpiredException $e) {
            return $e->render($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'WARRANTY_VERIFICATION_FAILED'
            ], $e->getCode() ?: 422);
        }
    }

    public function swap(Request $request)
    {
        $request->validate([
            'old_serial' => 'required|string',
            'new_serial' => 'required|string'
        ]);

        $businessId = $request->user()->business_id ?? 1;

        try {
            $this->warrantyManager->swapReplacementSerial($request->old_serial, $request->new_serial, $businessId);
            return response()->json(['message' => 'Serial swap completed successfully.']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'SERIAL_SWAP_FAILED'
            ], 422);
        }
    }
}
