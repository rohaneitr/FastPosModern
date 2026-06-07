<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function importProducts(Request $request)
    {
        $businessId = $request->user()->business_id;

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('business_id', $businessId)
            ]
        ]);

        $businessId = $request->user()->business_id;
        $locationId = $request->location_id;

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['message' => 'Could not read file.'], 500);
        }

        $header = fgetcsv($handle, 1000, ',');
        // Expected headers: Name, SKU, Purchase Price, Sell Price, Qty

        $importedCount = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($handle, $businessId, $locationId, $request, &$importedCount) {
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) < 5) continue; // Skip malformed rows

                    $name = trim($row[0]);
                    $sku = trim($row[1]);
                    $purchasePrice = (float) trim($row[2]);
                    $sellPrice = (float) trim($row[3]);
                    $qty = (float) trim($row[4]);

                    if (empty($name)) continue;

                    if (empty($sku)) {
                        $sku = strtoupper(substr($name, 0, 3)) . '-' . mt_rand(1000, 9999);
                    }

                    $productId = DB::table('products')->insertGetId([
                        'business_id' => $businessId,
                        'name' => $name,
                        'sku' => $sku,
                        'type' => 'single',
                        'barcode_type' => 'C128',
                        'has_serial_number' => false, // STRICT OVERRIDE AS PER DIRECTIVE
                        'is_active' => true,
                        'created_by' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Insert purchase price
                    DB::table('product_variations')->insert([
                        'product_id' => $productId,
                        'sku' => $sku,
                        'default_purchase_price' => $purchasePrice,
                        'sell_price_inc_tax' => $sellPrice,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Insert stock
                    DB::table('product_stocks')->insert([
                        'product_id' => $productId,
                        'location_id' => $locationId,
                        'qty_available' => $qty,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    $importedCount++;
                }
            }, 5);

            fclose($handle);

            return response()->json([
                'message' => "Successfully imported {$importedCount} products.",
                'count' => $importedCount
            ]);

        } catch (\Exception $e) {
            fclose($handle);
            Log::error('CSV Import Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }
}
