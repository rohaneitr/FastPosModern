<?php

namespace App\Modules\Clinical\Services;

use App\Modules\Clinical\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
// use Barryvdh\Snappy\Facades\SnappyPdf as PDF; // Using the system's standard PDF engine

class DiagnosticReportGenerator
{
    /**
     * Dynamically generates a PDF report based on a JSON parameter schema.
     */
    public function generateReportPdf(int $orderId): string
    {
        $order = DB::table('clinical_lab_orders')
            ->where('id', $orderId)
            ->first();

        if (!$order || $order->status !== 'Result_Uploaded') {
            throw new \Exception("Lab Order results are not available yet.");
        }

        // 1. Decrypt Patient PII safely
        $patient = Patient::findOrFail($order->patient_id);
        
        $testDetails = DB::table('products')->where('id', $order->product_id)->first();
        
        // 2. Decode Dynamic JSON Parameters
        $results = json_decode($order->parameters, true);

        // Fetch Reference Ranges from configuration or a dedicated lookup table
        $referenceRanges = $this->getReferenceRangesForTest($order->product_id);

        // 3. Evaluate Abnormalities
        $evaluatedResults = [];
        foreach ($results as $key => $value) {
            $range = $referenceRanges[$key] ?? null;
            $isAbnormal = false;
            
            if ($range) {
                $numValue = (float) $value;
                if ($numValue < $range['min'] || $numValue > $range['max']) {
                    $isAbnormal = true;
                }
            }

            $evaluatedResults[] = [
                'parameter_name' => $key,
                'result_value' => $value,
                'unit' => $range['unit'] ?? '',
                'reference_range' => $range ? "{$range['min']} - {$range['max']}" : 'N/A',
                'is_abnormal' => $isAbnormal,
            ];
        }

        // 4. Render the Universal Blade View
        // The View iterates over $evaluatedResults dynamically, wrapping abnormal values in <b> tags.
        $html = View::make('clinical.reports.universal_diagnostic_template', [
            'patient_name' => "{$patient->first_name} {$patient->last_name}",
            'patient_uid' => $patient->patient_uid,
            'test_name' => $testDetails->name,
            'order_number' => $order->order_number,
            'results' => $evaluatedResults,
            'doctor_remarks' => $order->doctor_remarks ? decrypt($order->doctor_remarks) : 'None',
            'date' => $order->completed_at,
        ])->render();

        // 5. Generate PDF
        // return PDF::loadHTML($html)->output();
        return $html; // Returning HTML string for demo purposes
    }

    private function getReferenceRangesForTest(int $productId): array
    {
        // In a real application, this would query a `clinical_reference_ranges` table.
        // Returning a mock map for demonstration.
        return [
            'Hemoglobin' => ['min' => 13.8, 'max' => 17.2, 'unit' => 'g/dL'],
            'WBC Count' => ['min' => 4.5, 'max' => 11.0, 'unit' => '10^9/L'],
            'Platelets' => ['min' => 150, 'max' => 450, 'unit' => '10^9/L'],
        ];
    }
}
