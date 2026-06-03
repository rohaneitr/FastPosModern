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
            ->where('business_id', $request->user()->business_id)
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
            'payment_method' => 'nullable|string|in:cash,card,bank_transfer',
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
}
