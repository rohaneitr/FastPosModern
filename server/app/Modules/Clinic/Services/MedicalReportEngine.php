<?php

namespace App\Modules\Clinic\Services;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MedicalReportEngine
{
    /**
     * Compile the HTML structure of the medical report before PDF conversion.
     */
    public function compileHTML($reportId, array $reportPayload, string $testType, array $patientMeta = []): string
    {
        $qrLink = URL::temporarySignedRoute(
            'api.public.diagnostic.verify',
            now()->addDays(30),
            ['token' => $reportId]
        );
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: 'Kalpurush', sans-serif; font-size: 12pt; color: #111827; }
                .header-banner { border-bottom: 2px solid #374151; padding-bottom: 10px; margin-bottom: 20px; }
                .patient-meta { display: table; width: 100%; margin-bottom: 20px; }
                .meta-col { display: table-cell; width: 33%; }
                .qr-code { text-align: right; }
                .abnormal-flag { font-weight: bold; color: #DC2626; }
                table.pathology { width: 100%; border-collapse: collapse; }
                table.pathology th, table.pathology td { padding: 8px; border-bottom: 1px solid #E5E7EB; text-align: left; }
                .radiology-text { margin-bottom: 15px; white-space: pre-wrap; }
                .signatures { display: table; width: 100%; margin-top: 50px; }
                .sig-box { display: table-cell; width: 50%; text-align: center; }
            </style>
        </head>
        <body>
            <div class=\"header-banner\">
                <h2>{$this->safeString($patientMeta['tenant_name'] ?? 'Medical Center')}</h2>
                <div class=\"patient-meta\">
                    <div class=\"meta-col\">
                        <strong>Patient:</strong> {$this->safeString($patientMeta['name'] ?? 'Unknown')}<br>
                        <strong>Age/Gender:</strong> {$this->safeString($patientMeta['age'] ?? '-')} / {$this->safeString($patientMeta['gender'] ?? '-')}
                    </div>
                    <div class=\"meta-col\">
                        <strong>Collected:</strong> {$this->safeString($patientMeta['collected_at'] ?? now()->toDateTimeString())}<br>
                        <strong>Reported:</strong> " . now()->toDateTimeString() . "
                    </div>
                    <div class=\"meta-col qr-code\">
                        <img src=\"https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($qrLink) . "\" alt=\"QR Code\" />
                        <br><small>Token: {$qrLink}</small>
                    </div>
                </div>
            </div>
            <div class=\"report-body\">
        ";

        if ($testType === 'Pathology') {
            $html .= $this->compilePathology($reportPayload['parameters'] ?? []);
        } elseif ($testType === 'Radiology') {
            $html .= $this->compileRadiology($reportPayload);
        }

        $html .= "
            </div>
            <div class=\"signatures\">
                <div class=\"sig-box\">
                    <p>___________________________</p>
                    <strong>Verified By</strong><br>
                    <small>Lab Technologist</small>
                </div>
                <div class=\"sig-box\">
                    <p>___________________________</p>
                    <strong>Authorized By</strong><br>
                    <small>Consultant Pathologist/Radiologist</small>
                </div>
            </div>
        </body>
        </html>
        ";

        return $html;
    }

    private function compilePathology(array $parameters): string
    {
        $html = "<table class=\"pathology\">
            <thead>
                <tr>
                    <th>Test Name</th>
                    <th>Result</th>
                    <th>Unit</th>
                    <th>Reference Range</th>
                </tr>
            </thead>
            <tbody>";

        foreach ($parameters as $param) {
            $name = $this->safeString($param['name'] ?? '');
            $value = $this->safeString($param['value'] ?? '');
            $unit = $this->safeString($param['unit'] ?? '');
            $range = $this->safeString($param['reference_range'] ?? '');

            $isAbnormal = $this->checkDeviation($value, $range);
            $valueClass = $isAbnormal ? 'abnormal-flag' : '';

            $html .= "<tr>
                <td>{$name}</td>
                <td class=\"{$valueClass}\">{$value}</td>
                <td>{$unit}</td>
                <td>{$range}</td>
            </tr>";
        }

        $html .= "</tbody></table>";
        return $html;
    }

    private function compileRadiology(array $payload): string
    {
        $history = $this->safeHtml($payload['clinical_history'] ?? '');
        $findings = $this->safeHtml($payload['findings'] ?? '');
        $impression = $this->safeHtml($payload['impression'] ?? '');

        return "
            <div class=\"radiology-text\">
                <h3>Clinical History</h3>
                <p>{$history}</p>
            </div>
            <div class=\"radiology-text\">
                <h3>Findings</h3>
                <p>{$findings}</p>
            </div>
            <div class=\"radiology-text\">
                <h3>Impression</h3>
                <p>{$impression}</p>
            </div>
        ";
    }

    private function checkDeviation(string $value, string $range): bool
    {
        // Naive parser for '12.0 - 16.0' ranges
        $val = floatval(preg_replace('/[^0-9.]/', '', $value));
        $parts = explode('-', $range);
        
        if (count($parts) === 2) {
            $min = floatval(trim($parts[0]));
            $max = floatval(trim($parts[1]));
            if ($val < $min || $val > $max) {
                return true;
            }
        }
        return false;
    }

    private function safeString($str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }

    private function safeHtml($str): string
    {
        // Simple markdown to HTML or nl2br mapping for structural radiology
        $str = htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
        return nl2br($str);
    }
}
