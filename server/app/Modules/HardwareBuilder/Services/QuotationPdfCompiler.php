<?php

namespace App\Modules\HardwareBuilder\Services;

class QuotationPdfCompiler
{
    /**
     * Compiles a standard print-ready HTML layout for the commercial quotation.
     * Note: Mpdf execution is decoupled, we return the strictly structured HTML.
     */
    public function compileHTML(array $payload, string $quotationId): string
    {
        $componentsHtml = '';
        foreach ($payload['components'] as $comp) {
            $name = htmlspecialchars($comp['name']);
            $sku = htmlspecialchars($comp['sku']);
            $qty = (int) $comp['quantity'];
            $price = number_format((float) $comp['locked_unit_price'], 2);
            $tax = number_format((float) $comp['locked_tax'], 2);
            $total = number_format(($comp['locked_unit_price'] + $comp['locked_tax']) * $qty, 2);
            $warranty = (int) ($comp['warranty_months'] ?? 0);
            
            $componentsHtml .= "
                <tr>
                    <td>{$sku}</td>
                    <td>{$name}<br><small>Warranty: {$warranty} Months</small></td>
                    <td style='text-align:center;'>{$qty}</td>
                    <td style='text-align:right;'>\${$price}</td>
                    <td style='text-align:right;'>\${$tax}</td>
                    <td style='text-align:right; font-weight:bold;'>\${$total}</td>
                </tr>
            ";
        }

        $subTotal = number_format((float) $payload['financials']['sub_total'], 2);
        $totalTax = number_format((float) $payload['financials']['total_tax'], 2);
        $grandTotal = number_format((float) $payload['financials']['grand_total'], 2);

        $barcodeData = "QUOTE-{$quotationId}";

        return "
        <html>
        <head>
            <style>
                body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; }
                .header { border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
                .title { font-size: 18pt; font-weight: bold; }
                .barcode-container { text-align: right; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; }
                th { background-color: #f4f4f4; text-align: left; }
                .totals-table { width: 40%; float: right; }
                .totals-table td { border: none; padding: 5px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <table style='border:none;'>
                    <tr>
                        <td style='border:none;'><span class='title'>COMMERCIAL QUOTATION</span><br>Quote ID: {$quotationId}</td>
                        <td style='border:none;' class='barcode-container'>
                            <barcode code='{$barcodeData}' type='C128A' size='1' height='0.5' />
                            <br><small>{$barcodeData}</small>
                        </td>
                    </tr>
                </table>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Component Description</th>
                        <th style='text-align:center;'>Qty</th>
                        <th style='text-align:right;'>Unit Price</th>
                        <th style='text-align:right;'>Tax</th>
                        <th style='text-align:right;'>Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$componentsHtml}
                </tbody>
            </table>

            <table class='totals-table'>
                <tr>
                    <td style='text-align:right;'>Subtotal:</td>
                    <td style='text-align:right;'>\${$subTotal}</td>
                </tr>
                <tr>
                    <td style='text-align:right;'>Tax:</td>
                    <td style='text-align:right;'>\${$totalTax}</td>
                </tr>
                <tr>
                    <td style='text-align:right; font-weight:bold;'>Grand Total:</td>
                    <td style='text-align:right; font-weight:bold;'>\${$grandTotal}</td>
                </tr>
            </table>
            
            <div style='clear:both; margin-top:50px;'>
                <h4>Terms & Conditions</h4>
                <p style='font-size:8pt;'>Prices are locked until the expiration date of this quotation. Component availability is subject to physical stock at the time of conversion.</p>
            </div>
        </body>
        </html>
        ";
    }
}
