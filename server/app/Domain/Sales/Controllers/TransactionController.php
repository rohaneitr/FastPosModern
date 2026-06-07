<?php

namespace App\Domain\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Notifications\InvoiceGenerated;
use App\Notifications\SmsChannel;
use Illuminate\Support\Facades\Notification;
use App\Jobs\SendInvoiceEmailJob;

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
            'items.*.fractional_ratio' => 'nullable|numeric|min:0.0001',
            'items.*.dosage_instructions' => 'nullable|string',
            'items.*.serial_numbers' => 'nullable|array',
            'items.*.serial_numbers.*' => 'string',
            'tax_rate' => 'required|numeric|min:0',
            // Discount (optional)
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            // Customer Details (optional, required if amount_paid < total)
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            // Payments
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz,advance',
            'save_as_quotation' => 'nullable|boolean',
            'convert_quotation_id' => 'nullable|integer',
            'send_sms' => 'nullable|boolean',
            'customer_phone' => 'nullable|string|max:20',
        ]);

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

            $isQuotation = $request->input('save_as_quotation', false);
            $invoiceNo = ($isQuotation ? 'QT-' : 'INV-') . time() . '-' . mt_rand(100, 999);

            $amountPaid = isset($validated['amount_paid']) ? (float)$validated['amount_paid'] : $finalTotal;
            $amountDue = max(0, $finalTotal - $amountPaid);

        try {
            $result = DB::transaction(function () use ($validated, $businessId, $request, $isQuotation, $invoiceNo, $amountPaid, $amountDue, $subtotal, $discountValue, $taxAmount, $finalTotal) {
                if (!$isQuotation && $amountDue > 0.01 && empty($validated['contact_id'])) {
                    throw new \Exception('Customer MUST be selected for credit sales / dues.');
                }

                if (!$isQuotation && $validated['payment_method'] === 'advance' && $amountPaid > 0) {
                    if (empty($validated['contact_id'])) {
                        throw new \Exception('Customer MUST be selected to use advance payment.');
                    }
                    
                    // Calculate available advance
                    $totalSales = DB::table('transactions')->where('contact_id', $validated['contact_id'])->where('type', 'sell')->where('status', 'final')->sum('final_total');
                    $totalReturns = DB::table('transactions')->where('contact_id', $validated['contact_id'])->where('type', 'sell_return')->where('status', 'final')->sum('final_total');
                    $totalPayments = DB::table('transaction_payments')
                        ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
                        ->where('transactions.contact_id', $validated['contact_id'])
                        ->sum('transaction_payments.amount');
                    $contact = DB::table('contacts')->where('id', $validated['contact_id'])->first();
                    $openingBalance = $contact->opening_balance ?? 0;
                    
                    $totalDue = ($totalSales + $openingBalance) - $totalPayments - $totalReturns;
                    $advanceBalance = $totalDue < 0 ? abs($totalDue) : 0;
                    
                    if ($amountPaid > $advanceBalance) {
                        throw new \Exception("Insufficient advance balance. Available: $advanceBalance, Required: $amountPaid");
                    }
                }

                $paymentStatus = 'paid';
                if ($isQuotation || $amountDue > 0.01) {
                    $paymentStatus = $amountPaid > 0 && !$isQuotation ? 'partial' : 'due';
                }

                $afterDiscount = $subtotal - $discountValue;

                // 1. Create Transaction
                $insertData = [
                    'business_id' => $businessId,
                    'location_id' => $validated['location_id'],
                    'created_by' => $request->user()->id,
                    'type' => $isQuotation ? 'sell' : 'sell',
                    'status' => $isQuotation ? 'draft' : 'final',
                    'is_quotation' => $isQuotation,
                    'invoice_no' => $invoiceNo,
                    'transaction_date' => Carbon::now(),
                    'total_before_tax' => $afterDiscount,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountValue,
                    'discount_type' => $validated['discount_type'] ?? null,
                    'final_total' => $finalTotal,
                    'amount_due' => $amountDue,
                    'payment_status' => $paymentStatus,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                if (!empty($validated['contact_id'])) {
                    $insertData['contact_id'] = $validated['contact_id'];
                }

                $transactionId = DB::table('transactions')->insertGetId($insertData);

                // 2. Insert Transaction Lines + decrement stock
                $lines = [];
                foreach ($validated['items'] as $item) {
                    $fractionalRatio = $item['fractional_ratio'] ?? 1.0;
                    $actualQty = $item['quantity'] * $fractionalRatio;

                    $lines[] = [
                        'transaction_id' => $transactionId,
                        'product_id' => $item['product_id'],
                        'quantity' => $actualQty,
                        'unit_price' => $item['price'],
                        'unit_price_inc_tax' => $item['price'] + ($item['price'] * $validated['tax_rate']),
                        'item_tax' => $item['price'] * $validated['tax_rate'],
                        'dosage_instructions' => $item['dosage_instructions'] ?? null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    if (!$isQuotation) {
                        $stock = DB::table('product_stocks')
                            ->where('product_id', $item['product_id'])
                            ->where('location_id', $validated['location_id'])
                            ->lockForUpdate()
                            ->first();

                        if (!$stock || $stock->qty_available < $actualQty) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'inventory' => ['Insufficient stock available for the requested product.']
                            ]);
                        }

                        DB::table('product_stocks')->where('id', $stock->id)
                            ->decrement('qty_available', $actualQty);
                    }

                    // Process serial numbers if provided and not quotation
                    if (!$isQuotation && !empty($item['serial_numbers'])) {
                        if (count($item['serial_numbers']) !== (int)$item['quantity']) {
                            throw new \Exception('Number of selected serials (' . count($item['serial_numbers']) . ') does not match cart quantity (' . $item['quantity'] . ') for product ID ' . $item['product_id']);
                        }

                        $updated = DB::table('product_serials')
                            ->where('business_id', $businessId)
                            ->where('product_id', $item['product_id'])
                            ->whereIn('serial_number', $item['serial_numbers'])
                            ->where('status', 'available')
                            ->update([
                                'status' => 'sold',
                                'transaction_id' => $transactionId,
                                'warranty_start_date' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                        
                        if ($updated !== count($item['serial_numbers'])) {
                            throw new \Exception('One or more selected serial numbers are no longer available. Please clear them and rescan.');
                        }
                    }
                }
                DB::table('transaction_lines')->insert($lines);

                // 3. Insert Payment
                if (!$isQuotation && $amountPaid > 0) {
                    DB::table('transaction_payments')->insert([
                        'transaction_id' => $transactionId,
                        'amount' => $amountPaid,
                        'method' => $validated['payment_method'],
                        'paid_on' => Carbon::now(),
                        'created_by' => $request->user()->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
                
                // Mark quotation as converted if applicable
                if (!$isQuotation && !empty($validated['convert_quotation_id'])) {
                    DB::table('transactions')
                        ->where('id', $validated['convert_quotation_id'])
                        ->where('business_id', $businessId)
                        ->update(['status' => 'converted']);
                }

                return $transactionId;
            }, 5);
            
            \Illuminate\Support\Facades\Cache::store('redis')->forget("dashboard_kpis_business_{$businessId}");

            // Dispatch Omnichannel Notification / Digital Receipt
            if (!$isQuotation) {
                $contact = null;
                $phone = null;
                if (!empty($validated['contact_id'])) {
                    $contact = DB::table('contacts')->where('id', $validated['contact_id'])->first();
                    $phone = $contact->mobile ?? null;
                }

                $shouldSendSms = !empty($validated['send_sms']);
                $phone = $phone ?? $validated['customer_phone'] ?? null;

                if ($shouldSendSms && $phone) {
                    $business = DB::table('businesses')->where('id', $businessId)->first();
                    $storeName = $business->name ?? 'Our Store';
                    
                    // Generate public receipt link
                    $origin = $request->header('origin') ?? config('app.frontend_url', 'http://localhost:3000');
                    $shortLink = $origin . "/receipt/" . $invoiceNo;
                    
                    $smsBody = "Thanks for shopping at {$storeName}! Total: " . round($finalTotal, 2) . ". View receipt: {$shortLink}";
                    
                    $smsService = app(\App\Services\SmsGatewayService::class);
                    $smsService->sendSms($phone, $smsBody, $businessId);
                } elseif ($contact && !empty($contact->mobile)) {
                    // Fallback to generic invoice notification if specific digital receipt was not toggled but contact has mobile
                    $business = DB::table('businesses')->where('id', $businessId)->first();
                    $storeName = $business->name ?? 'FastPOS Store';
                    
                    $transaction = (object)[
                        'invoice_no' => $invoiceNo,
                        'final_total' => $finalTotal,
                    ];
                    \Illuminate\Support\Facades\Notification::route(\App\Channels\SmsChannel::class, $contact->mobile)
                        ->notify(new \App\Notifications\InvoiceGenerated($transaction, $storeName));
                }
            }

            return response()->json([
                'message' => 'Sale processed successfully',
                'transaction_id' => $result,
                'invoice_no' => $invoiceNo,
                'subtotal' => round($subtotal, 2),
                'discount' => round($discountValue, 2),
                'tax' => round($taxAmount, 2),
                'final_total' => round($finalTotal, 2),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Checkout failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Dispatch email invoice asynchronously.
     */
    public function sendEmail(Request $request, $id)
    {
        $validated = $request->validate([
            'email' => 'required|email'
        ]);

        $businessId = $request->user()->business_id;

        $transaction = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        SendInvoiceEmailJob::dispatch($id, $validated['email']);

        return response()->json(['message' => 'Email queued for delivery!']);
    }

    /**
     * Get digital receipt publicly by invoice no
     */
    public function publicReceipt($invoice_no)
    {
        $transaction = DB::table('transactions')
            ->join('businesses', 'transactions.business_id', '=', 'businesses.id')
            ->where('invoice_no', $invoice_no)
            ->select('transactions.*', 'businesses.name as business_name', 'businesses.settings', 'businesses.branding')
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Receipt not found'], 404);
        }

        $lines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transaction_id', $transaction->id)
            ->select('transaction_lines.*', 'products.name')
            ->get();

        $payments = DB::table('transaction_payments')->where('transaction_id', $transaction->id)->get();

        return response()->json([
            'transaction' => $transaction,
            'lines' => $lines,
            'payments' => $payments
        ]);
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

        try {
            $transactionId = DB::transaction(function () use ($businessId, $request, $validated, $subtotal, $taxAmount, $finalTotal) {
                $txId = DB::table('transactions')->insertGetId([
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
                        'transaction_id' => $txId,
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

                return $txId;
            }, 5);

            return response()->json([
                'message' => 'Transaction held successfully',
                'transaction_id' => $transactionId,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to hold transaction', 'error' => $e->getMessage()], 500);
        }
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

        try {
            DB::beginTransaction();
            DB::table('transaction_lines')->where('transaction_id', $id)->delete();
            DB::table('transactions')->where('id', $id)->delete();
            DB::commit();
            return response()->json(['message' => 'Held transaction deleted']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete held transaction', 'error' => $e->getMessage()], 500);
        }
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
            'transactions.*.payment_method' => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz',
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

        if ($syncedCount > 0) {
            \Illuminate\Support\Facades\Cache::store('redis')->forget("dashboard_kpis_business_{$businessId}");
        }

        return response()->json([
            'message' => 'Sync completed',
            'synced_count' => $syncedCount,
            'failed' => $failedTransactions,
            'sync_timestamp' => Carbon::now()->toDateTimeString()
        ]);
    }
}
