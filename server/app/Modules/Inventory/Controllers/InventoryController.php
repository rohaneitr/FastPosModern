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

    public function pendingSourcing(Request $request)
    {
        $businessId = $request->user()->business_id;
        
        $pending = \Illuminate\Support\Facades\DB::table('transaction_lines')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->where('transaction_lines.sourcing_status', 'pending_sourcing')
            ->select(
                'transaction_lines.id as line_id',
                'transactions.invoice_no',
                'transactions.transaction_date',
                'products.name as product_name',
                'products.sku',
                'transaction_lines.quantity',
                'transaction_lines.unit_price'
            )
            ->orderBy('transactions.created_at', 'desc')
            ->get();
            
        return response()->json($pending);
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
        \Illuminate\Support\Facades\Gate::authorize('inventory.manage');
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
            'reason' => 'nullable|string',
            'adjustment_type' => 'nullable|string|in:decrease,increase'
        ]);

        $quantity = $validated['quantity'];
        $adjType = $request->input('adjustment_type');
        if ($adjType) {
            if ($adjType === 'decrease') {
                $quantity = -abs($quantity);
            } elseif ($adjType === 'increase') {
                $quantity = abs($quantity);
            }
        }

        try {
            $result = $action->execute(
                businessId: $businessId,
                userId: $request->user()->id,
                productId: $validated['product_id'],
                locationId: $validated['location_id'],
                quantity: $quantity,
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
        \Illuminate\Support\Facades\Gate::authorize('inventory.manage');
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'from_location_id' => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'to_location_id' => ['required', 'different:from_location_id', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'quantity' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);
        
        $reason = $request->input('note');

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

            // Increment or create destination with Pessimistic Locking
            $dest = DB::table('product_stocks')
                ->where('product_id', $validated['product_id'])
                ->where('location_id', $validated['to_location_id'])
                ->lockForUpdate()
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
                'reason' => 'Stock transfer: ' . ($reason ?? 'No reason'),
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
            
            DB::table('user_activities')->insert([
                'user_id' => $request->user()->id,
                'action' => 'Stock Transfer',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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

    /**
     * Get Inventory History (Audit Logs)
     */
    public function history(Request $request)
    {
        $businessId = $request->user()->business_id;

        // Fetch from activity_log parsing JSON properties
        $logs = DB::table('activity_log')
            ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
            ->where(function($q) {
                $q->where('activity_log.description', 'like', '%increase%')
                  ->orWhere('activity_log.description', 'like', '%decrease%')
                  ->orWhere('activity_log.description', 'like', '%transfer%')
                  ->orWhere('activity_log.log_name', 'inventory');
            })
            // Ideally we'd scope to business_id if activity_log stores it. 
            // Often it doesn't directly, so we scope via users if necessary, or assume the table has a tenant scope.
            // Spatie Activitylog might not have business_id natively unless extended.
            ->select('activity_log.*', 'users.first_name', 'users.last_name')
            ->orderByDesc('activity_log.created_at')
            ->limit(100)
            ->get();

        $formatted = $logs->map(function ($log) {
            $props = json_decode($log->properties, true) ?? [];
            $oldQty = $props['old']['qty_available'] ?? 0;
            $newQty = $props['attributes']['qty_available'] ?? 0;
            $diff = (float)$newQty - (float)$oldQty;

            $type = 'Adjust';
            if (stripos($log->description, 'transfer') !== false) {
                $type = 'Transfer';
            } elseif (stripos($log->description, 'increase') !== false || $diff > 0) {
                $type = 'Increase';
            } elseif (stripos($log->description, 'decrease') !== false || $diff < 0) {
                $type = 'Decrease';
            }

            return [
                'id' => $log->id,
                'date' => $log->created_at,
                'user' => $log->first_name ? trim($log->first_name . ' ' . $log->last_name) : 'System',
                'event_type' => $type,
                'quantity_adjusted' => $diff != 0 ? $diff : ($props['attributes']['quantity'] ?? null),
                'reason' => $props['attributes']['reason'] ?? $log->description,
                'properties' => $props
            ];
        });

        // Fallback to stock_adjustments if activity_log is empty (since our actions write there)
        if ($formatted->isEmpty()) {
            $adjustments = DB::table('stock_adjustments')
                ->leftJoin('users', 'stock_adjustments.adjusted_by', '=', 'users.id')
                ->leftJoin('products', 'stock_adjustments.product_id', '=', 'products.id')
                ->where('products.business_id', $businessId)
                ->select(
                    'stock_adjustments.id',
                    'stock_adjustments.created_at as date',
                    'users.first_name',
                    'users.last_name',
                    'stock_adjustments.quantity',
                    'stock_adjustments.reason',
                    'products.name as product_name'
                )
                ->orderByDesc('stock_adjustments.created_at')
                ->limit(100)
                ->get();

            $formatted = $adjustments->map(function ($adj) {
                $qty = (float)$adj->quantity;
                $type = 'Adjust';
                if (stripos($adj->reason, 'transfer') !== false) {
                    $type = 'Transfer';
                } elseif ($qty > 0) {
                    $type = 'Increase';
                } elseif ($qty < 0) {
                    $type = 'Decrease';
                }

                return [
                    'id' => 'adj_'.$adj->id,
                    'date' => $adj->date,
                    'user' => $adj->first_name ? trim($adj->first_name . ' ' . $adj->last_name) : 'System',
                    'event_type' => $type,
                    'quantity_adjusted' => $qty,
                    'reason' => ($adj->product_name ?? 'Product') . ' - ' . ($adj->reason ?? 'Manual Adjustment'),
                    'properties' => []
                ];
            });
        }

        return response()->json($formatted);
    }
}
