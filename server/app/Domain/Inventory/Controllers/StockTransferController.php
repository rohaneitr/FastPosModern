<?php

namespace App\Domain\Inventory\Controllers;

use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\StockTransferItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;

        $transfers = StockTransfer::with(['fromLocation', 'toLocation', 'creator'])
            ->where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transfers);
    }

    public function show(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $transfer = StockTransfer::with(['fromLocation', 'toLocation', 'creator', 'items.product'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        return response()->json($transfer);
    }

    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;
        $userId = $request->user()->id;

        $request->validate([
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.serial_numbers' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Validate Serials
            foreach ($request->items as $item) {
                $product = DB::table('products')->where('id', $item['product_id'])->where('business_id', $businessId)->first();
                if (!$product) throw new \Exception("Product not found");

                if ($product->has_serial_number) {
                    if (empty($item['serial_numbers']) || count($item['serial_numbers']) != $item['quantity']) {
                        throw new \Exception("Serial numbers count must match quantity for product {$product->name}");
                    }
                    
                    // Verify serials are available and optionally at the correct location (if location tracking exists)
                    foreach ($item['serial_numbers'] as $sn) {
                        $ps = DB::table('product_serials')
                            ->where('business_id', $businessId)
                            ->where('product_id', $product->id)
                            ->where('serial_number', $sn)
                            ->where('status', 'available')
                            ->first();

                        if (!$ps) {
                            throw new \Exception("Serial number {$sn} is not available for product {$product->name}");
                        }

                        // Also verify it's not already in transit
                        if ($ps->is_in_transit) {
                            throw new \Exception("Serial number {$sn} is already in transit");
                        }
                    }
                }
            }

            $transfer = StockTransfer::create([
                'business_id' => $businessId,
                'reference_no' => 'TRF-' . strtoupper(Str::random(6)),
                'from_location_id' => $request->from_location_id,
                'to_location_id' => $request->to_location_id,
                'status' => 'pending',
                'notes' => $request->notes,
                'total_items' => collect($request->items)->sum('quantity'),
                'created_by' => $userId,
            ]);

            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'serial_numbers' => $item['serial_numbers'] ?? null,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Transfer initiated successfully', 'transfer' => $transfer], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $request->validate([
            'status' => 'required|in:in_transit,completed'
        ]);

        $transfer = StockTransfer::with('items')->where('business_id', $businessId)->findOrFail($id);

        if ($transfer->status === 'completed') {
            return response()->json(['message' => 'Transfer is already completed'], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->status === 'in_transit' && $transfer->status === 'pending') {
                // Deduct from Source Location and Mark Serials as In-Transit
                foreach ($transfer->items as $item) {
                    $loc = DB::table('product_locations')
                        ->where('product_id', $item->product_id)
                        ->where('location_id', $transfer->from_location_id)
                        ->first();

                    if (!$loc || $loc->qty_available < $item->quantity) {
                        throw new \Exception("Insufficient stock for product ID {$item->product_id} at source location.");
                    }

                    DB::table('product_locations')
                        ->where('id', $loc->id)
                        ->decrement('qty_available', $item->quantity);

                    if ($item->serial_numbers) {
                        DB::table('product_serials')
                            ->where('business_id', $businessId)
                            ->where('product_id', $item->product_id)
                            ->whereIn('serial_number', $item->serial_numbers)
                            ->update([
                                'is_in_transit' => true,
                                'location_id' => $transfer->from_location_id // conceptually it just left this location
                            ]);
                    }
                }
                
                $transfer->update(['status' => 'in_transit']);

            } elseif ($request->status === 'completed' && $transfer->status === 'in_transit') {
                // Add to Destination Location and Mark Serials as Available at new location
                foreach ($transfer->items as $item) {
                    $loc = DB::table('product_locations')
                        ->where('product_id', $item->product_id)
                        ->where('location_id', $transfer->to_location_id)
                        ->first();

                    if ($loc) {
                        DB::table('product_locations')
                            ->where('id', $loc->id)
                            ->increment('qty_available', $item->quantity);
                    } else {
                        DB::table('product_locations')->insert([
                            'product_id' => $item->product_id,
                            'location_id' => $transfer->to_location_id,
                            'qty_available' => $item->quantity,
                        ]);
                    }

                    if ($item->serial_numbers) {
                        DB::table('product_serials')
                            ->where('business_id', $businessId)
                            ->where('product_id', $item->product_id)
                            ->whereIn('serial_number', $item->serial_numbers)
                            ->update([
                                'is_in_transit' => false,
                                'location_id' => $transfer->to_location_id,
                                'status' => 'available'
                            ]);
                    }
                }

                $transfer->update(['status' => 'completed']);
            } else {
                throw new \Exception("Invalid status transition.");
            }

            DB::commit();
            return response()->json(['message' => 'Transfer status updated to ' . $request->status, 'transfer' => $transfer]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
