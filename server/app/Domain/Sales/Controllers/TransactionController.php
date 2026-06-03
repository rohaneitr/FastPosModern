<?php

namespace App\Domain\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * Process a POS sale checkout.
     * Supports: split payments, discounts (fixed/percentage), and standard single-payment.
     */
    public function checkout(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0',
            // Discount (optional)
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            // Split payments: array of methods; falls back to single payment_method
            'payments' => 'nullable|array|min:1',
            'payments.*.method' => 'required_with:payments|string|in:cash,card,bank_transfer',
            'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
            'payment_method' => 'required_without:payments|string|in:cash,card,bank_transfer',
        ]);

        try {
            DB::beginTransaction();

            // Calculate subtotal
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += ($item['price'] * $item['quantity']);
            }

            // Apply discount
            $discountValue = 0;
            if (!empty($validated['discount_type']) && !empty($validated['discount_amount'])) {
                if ($validated['discount_type'] === 'percentage') {
                    $discountValue = $subtotal * ($validated['discount_amount'] / 100);
                } else {
                    $discountValue = min($validated['discount_amount'], $subtotal);
                }
            }
            $afterDiscount = $subtotal - $discountValue;

            $taxAmount = $afterDiscount * $validated['tax_rate'];
            $finalTotal = $afterDiscount + $taxAmount;

            $invoiceNo = 'INV-' . time() . '-' . mt_rand(100, 999);

            // 1. Create Transaction
            $transactionId = DB::table('transactions')->insertGetId([
                'business_id' => $businessId,
                'location_id' => $validated['location_id'],
                'created_by' => $request->user()->id,
                'type' => 'sell',
                'status' => 'final',
                'invoice_no' => $invoiceNo,
                'transaction_date' => Carbon::now(),
                'total_before_tax' => $afterDiscount,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountValue,
                'discount_type' => $validated['discount_type'] ?? null,
                'final_total' => $finalTotal,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // 2. Insert Transaction Lines + decrement stock
            $lines = [];
            foreach ($validated['items'] as $item) {
                $lines[] = [
                    'transaction_id' => $transactionId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'unit_price_inc_tax' => $item['price'] + ($item['price'] * $validated['tax_rate']),
                    'item_tax' => $item['price'] * $validated['tax_rate'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                $stock = DB::table('product_stocks')
                    ->where('product_id', $item['product_id'])
                    ->where('location_id', $validated['location_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->qty_available < $item['quantity']) {
                    throw new \Exception('Insufficient stock for product ID: ' . $item['product_id']);
                }

                DB::table('product_stocks')->where('id', $stock->id)
                    ->decrement('qty_available', $item['quantity']);
            }
            DB::table('transaction_lines')->insert($lines);

            // 3. Insert Payment(s) — supports split payments
            if (!empty($validated['payments'])) {
                $totalPaid = array_sum(array_column($validated['payments'], 'amount'));
                if (abs($totalPaid - $finalTotal) > 0.01) {
                    throw new \Exception('Split payment total (' . $totalPaid . ') does not match invoice total (' . $finalTotal . ')');
                }
                foreach ($validated['payments'] as $payment) {
                    DB::table('transaction_payments')->insert([
                        'transaction_id' => $transactionId,
                        'amount' => $payment['amount'],
                        'method' => $payment['method'],
                        'paid_on' => Carbon::now(),
                        'created_by' => $request->user()->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            } else {
                DB::table('transaction_payments')->insert([
                    'transaction_id' => $transactionId,
                    'amount' => $finalTotal,
                    'method' => $validated['payment_method'],
                    'paid_on' => Carbon::now(),
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Sale processed successfully',
                'transaction_id' => $transactionId,
                'invoice_no' => $invoiceNo,
                'subtotal' => round($subtotal, 2),
                'discount' => round($discountValue, 2),
                'tax' => round($taxAmount, 2),
                'final_total' => round($finalTotal, 2),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Hold (park) a transaction as draft for later resumption.
     */
    public function holdTransaction(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += ($item['price'] * $item['quantity']);
        }
        $taxAmount = $subtotal * $validated['tax_rate'];
        $finalTotal = $subtotal + $taxAmount;

        $transactionId = DB::table('transactions')->insertGetId([
            'business_id' => $businessId,
            'location_id' => $validated['location_id'],
            'created_by' => $request->user()->id,
            'type' => 'sell',
            'status' => 'draft',
            'invoice_no' => 'HOLD-' . time() . '-' . mt_rand(100, 999),
            'transaction_date' => Carbon::now(),
            'total_before_tax' => $subtotal,
            'tax_amount' => $taxAmount,
            'final_total' => $finalTotal,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $lines = [];
        foreach ($validated['items'] as $item) {
            $lines[] = [
                'transaction_id' => $transactionId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'unit_price_inc_tax' => $item['price'] + ($item['price'] * $validated['tax_rate']),
                'item_tax' => $item['price'] * $validated['tax_rate'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        DB::table('transaction_lines')->insert($lines);

        return response()->json([
            'message' => 'Transaction held successfully',
            'transaction_id' => $transactionId,
        ], 201);
    }

    public function heldTransactions(Request $request)
    {
        $businessId = $request->user()->business_id;

        $held = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'draft')
            ->orderByDesc('created_at')
            ->get();

        if ($held->isEmpty()) {
            return response()->json($held);
        }

        $heldIds = $held->pluck('id')->toArray();

        $allLines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->whereIn('transaction_lines.transaction_id', $heldIds)
            ->select('transaction_lines.*', 'products.name as product_name')
            ->get()
            ->groupBy('transaction_id');

        foreach ($held as &$tx) {
            $tx->lines = $allLines->get($tx->id, collect())->values()->all();
        }

        return response()->json($held);
    }

    /**
     * Delete a held transaction (cancel the hold).
     */
    public function deleteHeld(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $tx = DB::table('transactions')
            ->where('id', $id)->where('business_id', $businessId)->where('status', 'draft')
            ->first();

        if (!$tx) {
            return response()->json(['message' => 'Held transaction not found'], 404);
        }

        DB::table('transaction_lines')->where('transaction_id', $id)->delete();
        DB::table('transactions')->where('id', $id)->delete();

        return response()->json(['message' => 'Held transaction deleted']);
    }

    /**
     * Process bulk offline sales pushed from mobile.
     */
    public function syncPush(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'transactions' => 'required|array',
            'transactions.*.invoice_no' => 'required|string',
            'transactions.*.location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('business_id', $businessId)
            ],
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.items' => 'required|array|min:1',
            'transactions.*.items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $businessId)
            ],
            'transactions.*.items.*.quantity' => 'required|numeric|min:1',
            'transactions.*.items.*.price' => 'required|numeric|min:0',
            'transactions.*.payment_method' => 'required|string|in:cash,card,bank_transfer',
            'transactions.*.tax_rate' => 'required|numeric|min:0',
        ]);

        $syncedCount = 0;
        $failedTransactions = [];

        foreach ($validated['transactions'] as $tx) {
            try {
                DB::beginTransaction();

                // Idempotency Check: if invoice_no already exists, skip it
                $exists = DB::table('transactions')
                    ->where('business_id', $businessId)
                    ->where('invoice_no', $tx['invoice_no'])
                    ->exists();

                if ($exists) {
                    DB::rollBack();
                    continue; // Already synced
                }

                $subtotal = 0;
                foreach ($tx['items'] as $item) {
                    $subtotal += ($item['price'] * $item['quantity']);
                }
                $taxAmount = $subtotal * $tx['tax_rate'];
                $finalTotal = $subtotal + $taxAmount;

                $transactionId = DB::table('transactions')->insertGetId([
                    'business_id' => $businessId,
                    'location_id' => $tx['location_id'],
                    'created_by' => $request->user()->id,
                    'type' => 'sell',
                    'status' => 'final',
                    'invoice_no' => $tx['invoice_no'], // Use mobile generated invoice ID
                    'transaction_date' => $tx['transaction_date'], // Honor offline timestamp
                    'total_before_tax' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'final_total' => $finalTotal,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $lines = [];
                foreach ($tx['items'] as $item) {
                    $lines[] = [
                        'transaction_id' => $transactionId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'unit_price_inc_tax' => $item['price'] + ($item['price'] * $tx['tax_rate']),
                        'item_tax' => $item['price'] * $tx['tax_rate'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    // Decrement inventory (allow negative if necessary for offline sync)
                    $stock = DB::table('product_stocks')
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $tx['location_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        // We intentionally do NOT throw an exception here if qty < requested
                        // Offline transactions are historical facts. The stock must go negative to reflect shrinkage.
                        DB::table('product_stocks')
                            ->where('id', $stock->id)
                            ->decrement('qty_available', $item['quantity']);
                    }
                }
                DB::table('transaction_lines')->insert($lines);

                DB::table('transaction_payments')->insert([
                    'transaction_id' => $transactionId,
                    'amount' => $finalTotal,
                    'method' => $tx['payment_method'],
                    'paid_on' => $tx['transaction_date'],
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                DB::commit();
                $syncedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $failedTransactions[] = [
                    'invoice_no' => $tx['invoice_no'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Sync completed',
            'synced_count' => $syncedCount,
            'failed' => $failedTransactions,
            'sync_timestamp' => Carbon::now()->toDateTimeString()
        ]);
    }
}
