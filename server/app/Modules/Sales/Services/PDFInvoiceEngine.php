<?php

namespace App\Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PDFInvoiceEngine
{
    protected function initializeMpdf()
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'fontDir' => array_merge($fontDirs, [
                storage_path('app/fonts'),
            ]),
            'fontdata' => $fontData + [
                'kalpurush' => [
                    'R' => 'Kalpurush.ttf',
                    'B' => 'Kalpurush.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ]
            ],
            'default_font' => 'kalpurush',
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);

        return $mpdf;
    }

    public function renderInvoiceHTML($transactionId, $businessId)
    {
        // For mockup purposes, return HTML
        return "<html><body><h1>Invoice #{$transactionId}</h1><p>Test Invoice Engine Generation.</p></body></html>";
    }

    public function streamInvoice(int $transactionId, int $businessId): string
    {
        $transaction = DB::table('transactions')
            ->where('id', $transactionId)
            ->where('business_id', $businessId)
            ->first();

        if (!$transaction) {
            throw new \Exception('Transaction not found.');
        }

        $disk = Storage::disk('local');
        $fileName = "secure_invoices/invoice_{$transaction->invoice_no}.pdf";

        if ($disk->exists($fileName)) {
            return $disk->path($fileName);
        }

        $htmlContent = $this->renderInvoiceHTML($transactionId, $businessId);
        $mpdf = $this->initializeMpdf();
        $mpdf->WriteHTML($htmlContent);
        $pdfBinary = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        $disk->put($fileName, $pdfBinary);

        return $disk->path($fileName);
    }
}
