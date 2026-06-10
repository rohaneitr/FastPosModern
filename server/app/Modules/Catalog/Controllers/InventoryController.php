<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Catalog\Models\Product;

class InventoryController extends Controller
{
    /**
     * Transfer inventory between locations.
     * ACID Compliant with Pessimistic Locking.
     */
    public function transfer(Request $request)
    {
        // Defense-in-Depth: enforce permission at controller layer independently of route middleware.
        // This ensures Inventory Transfer cannot be accessed regardless of route misconfiguration.
        \Illuminate\Support\Facades\Gate::authorize('inventory.manage');

        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'source_location_id' => 'required|integer|exists:locations,id',
            'destination_location_id' => 'required|integer|exists:locations,id|different:source_location_id',
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::beginTransaction();

            $productId = $validated['product_id'];
            $sourceLocId = $validated['source_location_id'];
            $destLocId = $validated['destination_location_id'];
            $quantity = $validated['quantity'];

            // 1. Lock the Source Stock Row for Update
            $sourceStock = DB::table('product_stocks')
                ->where('product_id', $productId)
                ->where('location_id', $sourceLocId)
                ->lockForUpdate()
                ->first();

            if (!$sourceStock || $sourceStock->qty_available < $quantity) {
                throw new \Exception("Insufficient stock in source location for product ID: {$productId}");
            }

            // 2. Deduct from Source
            DB::table('product_stocks')
                ->where('id', $sourceStock->id)
                ->update([
                    'qty_available' => $sourceStock->qty_available - $quantity,
                    'updated_at' => now(),
                ]);

            // 3. Add to Destination (or create if not exists, lock it as well if it exists)
            $destStock = DB::table('product_stocks')
                ->where('product_id', $productId)
                ->where('location_id', $destLocId)
                ->lockForUpdate()
                ->first();

            if ($destStock) {
                DB::table('product_stocks')
                    ->where('id', $destStock->id)
                    ->update([
                        'qty_available' => $destStock->qty_available + $quantity,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('product_stocks')->insert([
                    'product_id' => $productId,
                    'location_id' => $destLocId,
                    'qty_available' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Log in Stock Ledgers (Audit Trail)
            // Deduct from Source Ledger
            DB::table('stock_ledgers')->insert([
                'business_id' => $businessId,
                'product_id' => $productId,
                'transaction_type' => 'transfer_out',
                'quantity' => -$quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add to Destination Ledger
            DB::table('stock_ledgers')->insert([
                'business_id' => $businessId,
                'product_id' => $productId,
                'transaction_type' => 'transfer_in',
                'quantity' => $quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock transfer successful',
                'transferred_quantity' => $quantity
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Transfer failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
