<?php

namespace Tests\Feature\Clinic;

use Tests\TestCase;
use App\Modules\Clinic\Services\MedicalReportEngine;

class DiagnosticReportCompilerTest extends TestCase
{
    public function test_abnormal_value_highlighting()
    {
        $engine = new MedicalReportEngine();

        $payload = [
            'parameters' => [
                [
                    'name' => 'Hemoglobin',
                    'value' => '9.0',
                    'unit' => 'g/dL',
                    'reference_range' => '12.0 - 16.0'
                ]
            ]
        ];

        $html = $engine->compileHTML('REPORT-123', $payload, 'Pathology');

        // Assert the abnormality was detected and flagged
        $this->assertStringContainsString('class="abnormal-flag">9.0</td>', $html);
    }

    public function test_cryptographic_qr_token_presence()
    {
        $engine = new MedicalReportEngine();

        $payload = [
            'clinical_history' => 'Fever',
            'findings' => 'Normal',
            'impression' => 'Healthy'
        ];

        $html = $engine->compileHTML('REPORT-XYZ-999', $payload, 'Radiology', [
            'tenant_name' => 'City Hospital',
            'name' => 'John Doe',
            'age' => '30',
            'gender' => 'Male'
        ]);

        // Assert QR code image tag and token are present in the output
        $this->assertStringContainsString('https://api.qrserver.com/v1/create-qr-code/', $html);
        $this->assertStringContainsString('REPORT-XYZ-999', $html);
        $this->assertStringContainsString('api/v1/public/diagnostic/verify', $html); // temporarySignedRoute base path
        $this->assertStringContainsString('signature=', $html); // Sanctum/Laravel signed route signature
    }
}
