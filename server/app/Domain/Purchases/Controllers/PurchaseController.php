<?php

namespace App\Domain\Purchases\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index(Request $request)
    {
        $purchases = DB::table('transactions')
            ->tenant()
            ->where('type', 'purchase')
            ->orderBy('transaction_date', 'desc')
            ->paginate(20);

        return response()->json($purchases);
    }

    /**
     * Store a newly created purchase in storage.
     */
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('business_id', $businessId)
            ],
            'contact_id' => [
                'required',
                Rule::exists('contacts', 'id')->where('business_id', $businessId)
            ], // Supplier
            'status' => 'required|in:received,pending,ordered',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $businessId)
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.serial_numbers' => 'nullable|string',
            'payment_method' => 'nullable|string|in:cash,card,bank_transfer,bkash,sslcommerz',
            'amount_paid' => 'nullable|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += ($item['purchase_price'] * $item['quantity']);
            }

            // 1. Create Transaction for Purchase
            $insertData = [
                'business_id' => $request->user()->business_id,
                'location_id' => $validated['location_id'],
                'created_by' => $request->user()->id,
                'type' => 'purchase',
                'status' => $validated['status'],
                'invoice_no' => 'PO-' . time(),
                'transaction_date' => Carbon::now(),
                'total_before_tax' => $subtotal,
                'tax_amount' => 0,
                'final_total' => $subtotal,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            // contact_id is added by migration 2026_06_02_141500
            if (Schema::hasColumn('transactions', 'contact_id')) {
                $insertData['contact_id'] = $validated['contact_id'];
            }

            $transactionId = DB::table('transactions')->insertGetId($insertData);

            // 2. Insert Lines & Update Stock if Received
            $lines = [];
            foreach ($validated['items'] as $item) {
                $lines[] = [
                    'transaction_id' => $transactionId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['purchase_price'],
                    'unit_price_inc_tax' => $item['purchase_price'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                // Add to inventory if received
                if ($validated['status'] === 'received') {
                    $stock = DB::table('product_stocks')
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $validated['location_id'])
                        ->first();
                    
                    if ($stock) {
                        DB::table('product_stocks')
                            ->where('id', $stock->id)
                            ->increment('qty_available', $item['quantity']);
                    } else {
                        DB::table('product_stocks')->insert([
                            'product_id' => $item['product_id'],
                            'location_id' => $validated['location_id'],
                            'qty_available' => $item['quantity'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }

                // Process Serial Numbers
                if (!empty($item['serial_numbers'])) {
                    $serials = array_map('trim', explode(',', $item['serial_numbers']));
                    $serials = array_filter($serials);
                    
                    if (count($serials) !== (int)$item['quantity']) {
                        throw new \Exception('Number of serials provided (' . count($serials) . ') does not match expected quantity (' . $item['quantity'] . ') for product ID ' . $item['product_id']);
                    }
                    
                    $serialRecords = [];
                    foreach ($serials as $sn) {
                        $serialRecords[] = [
                            'business_id' => $request->user()->business_id,
                            'product_id' => $item['product_id'],
                            'serial_number' => $sn,
                            'status' => 'available',
                            'purchase_id' => $transactionId,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ];
                    }
                    DB::table('product_serials')->insert($serialRecords);
                }
            }
            DB::table('transaction_lines')->insert($lines);

            // 3. Payment
            if (!empty($validated['amount_paid']) && $validated['amount_paid'] > 0) {
                DB::table('transaction_payments')->insert([
                    'transaction_id' => $transactionId,
                    'amount' => $validated['amount_paid'],
                    'method' => $validated['payment_method'] ?? 'cash',
                    'paid_on' => Carbon::now(),
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Purchase created successfully',
                'transaction_id' => $transactionId,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Purchase creation failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Process a Purchase Return
     */
    public function purchaseReturn(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'transaction_id' => [
                'required',
                Rule::exists('transactions', 'id')->where('business_id', $businessId)
            ],
            'return_amount' => 'required|numeric|min:0',
            'lines' => 'required|array', // Array of product_id and qty to return
            'lines.*.product_id' => 'required|integer',
            'lines.*.quantity' => 'required|numeric|min:1',
            'lines.*.serial_numbers' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $original = DB::table('transactions')
                ->where('id', $validated['transaction_id'])
                ->where('business_id', $businessId)
                ->first();

            if (!$original) {
                return response()->json(['message' => 'Transaction not found or access denied'], 404);
            }

            // Create Return Transaction
            $returnTxId = DB::table('transactions')->insertGetId([
                'business_id' => $request->user()->business_id,
                'location_id' => $original->location_id,
                'type' => 'purchase_return',
                'status' => 'final',
                'contact_id' => $original->contact_id,
                'return_parent_id' => $original->id,
                'total_before_tax' => $validated['return_amount'],
                'tax_amount' => 0,
                'final_total' => $validated['return_amount'],
                'transaction_date' => now(),
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            // Deduct Inventory and create return lines
            foreach ($validated['lines'] as $line) {
                DB::table('transaction_lines')->insert([
                    'transaction_id' => $returnTxId,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => 0,
                    'unit_price_inc_tax' => 0,
                    'item_tax' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('product_stocks')
                    ->where('product_id', $line['product_id'])
                    ->where('location_id', $original->location_id)
                    ->decrement('qty_available', $line['quantity']);
                    
                // Phase 8: Revert/Delete Serials
                if (!empty($line['serial_numbers'])) {
                    DB::table('product_serials')
                        ->where('business_id', $businessId)
                        ->where('product_id', $line['product_id'])
                        ->whereIn('serial_number', $line['serial_numbers'])
                        ->where('status', 'available')
                        ->delete(); // Remove the serials returned to supplier
                }
            }

            DB::commit();
            return response()->json(['message' => 'Purchase return processed successfully', 'return_id' => $returnTxId]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process return', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display a specific purchase.
     */
    public function show(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $purchase = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->where('type', 'purchase')
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        $lines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transaction_lines.transaction_id', $id)
            ->select('transaction_lines.*', 'products.name as product_name')
            ->get();

        $serials = DB::table('product_serials')
            ->where('purchase_id', $id)
            ->get();

        $contact = DB::table('contacts')->where('id', $purchase->contact_id)->first();

        $purchase->lines = $lines;
        $purchase->serials = $serials;
        $purchase->supplier = $contact;

        return response()->json($purchase);
    }
}
