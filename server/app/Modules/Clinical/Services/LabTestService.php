<?php

namespace App\Modules\Clinical\Services;

use App\Modules\Inventory\Services\BOMDeductionService;
use Illuminate\Support\Facades\DB;
use Exception;

class LabTestService
{
    protected BOMDeductionService $bomDeductionService;

    public function __construct(BOMDeductionService $bomDeductionService)
    {
        $this->bomDeductionService = $bomDeductionService;
    }

    /**
     * Submits a diagnostic result and triggers the reagent consumption engine.
     */
    public function uploadResults(int $orderId, array $parameters, ?string $doctorRemarks = null): void
    {
        DB::transaction(function () use ($orderId, $parameters, $doctorRemarks) {
            $order = DB::table('clinical_lab_orders')->where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                throw new Exception("Lab Order not found.");
            }

            if ($order->status === 'Result_Uploaded') {
                throw new Exception("Results for this order have already been uploaded.");
            }

            // 1. Reagent Consumption via BOM Engine
            // A Lab Test (e.g. CBC) is mapped as a Composite Product in our system.
            // When we complete the test, we recursively deduct the reagents/chemicals used.
            $this->bomDeductionService->deductForOrder(
                $order->business_id,
                $order->product_id,
                1, // Typically 1 test consumes 1 BOM equivalent of reagents
                "Lab Order #{$order->order_number}"
            );

            // 2. Commit Clinical Results
            DB::table('clinical_lab_orders')->where('id', $orderId)->update([
                'status' => 'Result_Uploaded',
                'parameters' => json_encode($parameters),
                'doctor_remarks' => $doctorRemarks ? encrypt($doctorRemarks) : null, // Encrypt remarks at rest
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
