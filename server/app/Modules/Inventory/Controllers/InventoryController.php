<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /**
     * Get stock levels across all locations
     */
    public function stock(Request $request)
    {
        $stocks = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->join('locations', 'product_stocks.location_id', '=', 'locations.id')
            ->where('locations.business_id', $request->user()->business_id)
            ->select(
                'product_stocks.id',
                'products.name as product_name',
                'locations.name as location_name',
                'product_stocks.qty_available',
                'products.sku'
            )
            ->orderBy('products.name')
            ->paginate(50);
            
        return response()->json($stocks);
    }

    /**
     * Get inventory layers mapped by product for the control panel.
     */
    public function layers(Request $request)
    {
        $layers = DB::table('inventory_layers')
            ->join('products', 'inventory_layers.product_id', '=', 'products.id')
            ->where('inventory_layers.business_id', $request->user()->business_id)
            ->where(function ($query) {
                $query->where('inventory_layers.remaining_qty', '>', 0)
                      ->orWhere('inventory_layers.remaining_qty', '<', 0);
            })
            ->select(
                'inventory_layers.id',
                'products.name as product_name',
                'products.sku',
                'inventory_layers.product_id',
                'inventory_layers.original_qty',
                'inventory_layers.remaining_qty',
                'inventory_layers.unit_cost',
                'inventory_layers.created_at'
            )
            ->orderBy('products.name', 'asc')
            ->orderBy('inventory_layers.created_at', 'asc')
            ->get()
            ->groupBy('product_name');

        return response()->json($layers);
    }

    /**
     * Manual Stock Adjustment
     */
    public function adjustStock(Request $request, \App\Modules\Inventory\Actions\AdjustStockAction $action)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('business_id', $businessId)
            ],
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $businessId)
            ],
            'quantity' => 'required|numeric', // positive for increase, negative for decrease
            'reason' => 'nullable|string'
        ]);

        try {
            $result = $action->execute(
                businessId: $businessId,
                userId: $request->user()->id,
                productId: $validated['product_id'],
                locationId: $validated['location_id'],
                quantity: $validated['quantity'],
                reason: $validated['reason'] ?? null
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Adjustment failed', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Transfer stock between locations.
     */
    public function transferStock(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'from_location_id' => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'to_location_id' => ['required', 'different:from_location_id', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Lock source stock
            $source = DB::table('product_stocks')
                ->where('product_id', $validated['product_id'])
                ->where('location_id', $validated['from_location_id'])
                ->lockForUpdate()
                ->first();

            if (!$source || $source->qty_available < $validated['quantity']) {
                DB::rollBack();
                return response()->json(['message' => 'Insufficient stock at source location.'], 422);
            }

            // Decrement source
            DB::table('product_stocks')
                ->where('id', $source->id)
                ->decrement('qty_available', $validated['quantity']);

            // Increment or create destination
            $dest = DB::table('product_stocks')
                ->where('product_id', $validated['product_id'])
                ->where('location_id', $validated['to_location_id'])
                ->first();

            if ($dest) {
                DB::table('product_stocks')->where('id', $dest->id)
                    ->increment('qty_available', $validated['quantity']);
            } else {
                DB::table('product_stocks')->insert([
                    'product_id' => $validated['product_id'],
                    'location_id' => $validated['to_location_id'],
                    'qty_available' => $validated['quantity'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // Audit log — two entries (out + in)
            $auditBase = [
                'product_id' => $validated['product_id'],
                'adjusted_by' => $request->user()->id,
                'reason' => 'Stock transfer: ' . ($validated['reason'] ?? 'No reason'),
                'created_at' => now(), 'updated_at' => now(),
            ];

            DB::table('stock_adjustments')->insert(array_merge($auditBase, [
                'location_id' => $validated['from_location_id'],
                'quantity' => -$validated['quantity'],
                'qty_before' => $source->qty_available,
                'qty_after' => $source->qty_available - $validated['quantity'],
            ]));

            DB::table('stock_adjustments')->insert(array_merge($auditBase, [
                'location_id' => $validated['to_location_id'],
                'quantity' => $validated['quantity'],
                'qty_before' => $dest ? $dest->qty_available : 0,
                'qty_after' => ($dest ? $dest->qty_available : 0) + $validated['quantity'],
            ]));

            DB::commit();

            return response()->json(['message' => 'Stock transferred successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transfer failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get low stock products (below configurable threshold).
     */
    public function lowStock(Request $request)
    {
        $businessId = $request->user()->business_id;
        $threshold = $request->query('threshold', 10);

        $lowStockItems = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->join('locations', 'product_stocks.location_id', '=', 'locations.id')
            ->where('products.business_id', $businessId)
            ->whereNull('products.deleted_at')
            ->where('product_stocks.qty_available', '<', $threshold)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                'locations.name as location_name',
                'product_stocks.qty_available'
            )
            ->orderBy('product_stocks.qty_available', 'asc')
            ->get();

        return response()->json([
            'threshold' => (int) $threshold,
            'count' => $lowStockItems->count(),
            'items' => $lowStockItems,
        ]);
    }
}
