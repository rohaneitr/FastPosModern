<?php

namespace App\Modules\HardwareBuilder\Services;

use App\Modules\HardwareBuilder\Exceptions\QuotationStockDeficitException;
use Illuminate\Support\Facades\DB;
use Exception;

class QuotationConversionService
{
    protected $validationEngine;

    public function __construct(BuilderValidationEngine $validationEngine)
    {
        $this->validationEngine = $validationEngine;
    }

    public function convertToSale($quotationId)
    {
        return DB::transaction(function () use ($quotationId) {
            $quote = DB::table('commercial_quotations')
                ->where('id', $quotationId)
                ->lockForUpdate()
                ->first();

            if (!$quote) {
                throw new Exception("Quotation not found.", 404);
            }

            if ($quote->status !== 'Draft' && $quote->status !== 'Sent') {
                throw new Exception("Quotation cannot be converted (Status: {$quote->status}).", 422);
            }

            if (now()->greaterThan($quote->valid_until)) {
                // Update status to expired
                DB::table('commercial_quotations')->where('id', $quotationId)->update(['status' => 'Expired']);
                throw new Exception("Quotation has expired.", 410); // HTTP 410 Gone
            }

            $payload = json_decode($quote->build_payload, true);
            $components = $payload['components'] ?? [];

            // Remediation 1: Mandatory Conversion Re-Validation Hook
            // Ensure no metadata changes invalidated the build
            $this->validationEngine->validate($components);

            // Remediation 2: Defensive Stock Verification Gate
            $deficits = [];
            foreach ($components as $comp) {
                $product = DB::table('products')->where('id', $comp['product_id'])->lockForUpdate()->first();
                if (!$product || $product->stock_qty < $comp['quantity']) {
                    $deficits[] = [
                        'sku' => $comp['sku'],
                        'requested' => $comp['quantity'],
                        'available' => $product ? $product->stock_qty : 0
                    ];
                }
            }

            if (count($deficits) > 0) {
                throw new QuotationStockDeficitException($deficits);
            }

            // Perform inventory deductions and finalize sale logically
            foreach ($components as $comp) {
                DB::table('products')->where('id', $comp['product_id'])->decrement('stock_qty', $comp['quantity']);
            }

            DB::table('commercial_quotations')->where('id', $quotationId)->update(['status' => 'ConvertedToSale']);

            return true;
        });
    }
}
