<?php

namespace App\Modules\Sales\Controllers;

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
        $documentType = $request->input('document_type', $request->input('save_as_quotation') ? 'Quotation' : 'Invoice');
        $isPosting = $documentType === 'Invoice';

        if ($isPosting) {
            $business = DB::table('businesses')->where('id', $businessId)->first();
            $settings = $business->settings ? json_decode($business->settings, true) : [];
            $enforceDeviceLock = $settings['pos_enforce_device_lock'] ?? true;
            $enforceCashControl = $settings['pos_enforce_strict_cash_control'] ?? true;

            if ($enforceCashControl) {
                $deviceHash = $request->header('X-Device-Hash') ?? $request->input('device_hash');
                
                $query = DB::table('cash_registers')
                    ->where('opened_by_user_id', $request->user()->id)
                    ->where('status', 'open');
                    
                if ($enforceDeviceLock) {
                    $query->where('device_hash', $deviceHash);
                }
                
                $activeSession = $query->first();

                if (!$activeSession) {
                    $msg = $enforceDeviceLock 
                        ? 'FPM Security: POS checkout blocked. Cash register drawer is closed, bound to another device, or currently suspending.'
                        : 'FPM Security: POS checkout blocked. Cash register drawer is closed or currently suspending.';
                    return response()->json(['message' => $msg], 422);
                }
            }
        }

        $validated = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0',
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz,advance,store_credit',
            'save_as_quotation' => 'nullable|boolean',
            'document_type' => 'nullable|string|in:Invoice,ProformaInvoice,Quotation',
            'convert_quotation_id' => 'nullable|integer',
            'send_sms' => 'nullable|boolean',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $subtotal = \App\Modules\Sales\Services\FinancialCalculator::of(0);
        foreach ($validated['items'] as $item) {
            $lineTotal = \App\Modules\Sales\Services\FinancialCalculator::calculateLineTotal($item['price'], $item['quantity']);
            $subtotal = $subtotal->plus($lineTotal);
        }

        $discountValue = \App\Modules\Sales\Services\FinancialCalculator::of(0);
        if (!empty($validated['discount_type']) && !empty($validated['discount_amount'])) {
            if ($validated['discount_type'] === 'percentage') {
                $discountValue = \App\Modules\Sales\Services\FinancialCalculator::calculatePercentageDiscount($subtotal, $validated['discount_amount']);
            } else {
                $discountValue = \App\Modules\Sales\Services\FinancialCalculator::of($validated['discount_amount']);
                if ($discountValue->isGreaterThan($subtotal)) {
                    $discountValue = $subtotal;
                }
            }
        }
        $afterDiscount = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($subtotal, $discountValue);

        $taxAmount = \App\Modules\Sales\Services\FinancialCalculator::calculateTax($afterDiscount, $validated['tax_rate']);
        $finalTotal = $afterDiscount->plus($taxAmount);

        $invoiceNo = (!$isPosting ? 'QT-' : 'INV-') . time() . '-' . mt_rand(100, 999);

        $amountPaid = isset($validated['amount_paid']) ? \App\Modules\Sales\Services\FinancialCalculator::of($validated['amount_paid']) : $finalTotal;
        $amountDue = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($finalTotal, $amountPaid);

        try {
            $result = DB::transaction(function () use ($validated, $businessId, $request, $isPosting, $documentType, $invoiceNo, $amountPaid, $amountDue, $subtotal, $discountValue, $taxAmount, $finalTotal) {
                if ($isPosting && $amountDue->isGreaterThan(0.01) && empty($validated['contact_id'])) {
                    throw new \Exception('Customer MUST be selected for credit sales / dues.');
                }

                if ($isPosting && $validated['payment_method'] === 'advance' && $amountPaid->isGreaterThan(0)) {
                    if (empty($validated['contact_id'])) {
                        throw new \Exception('Customer MUST be selected to use advance payment.');
                    }
                    
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
                    
                    if ($amountPaid->isGreaterThan($advanceBalance)) {
                        throw new \Exception("Insufficient advance balance. Available: $advanceBalance, Required: " . (string)$amountPaid);
                    }
                }

                if ($isPosting && $validated['payment_method'] === 'store_credit' && $amountPaid->isGreaterThan(0)) {
                    if (empty($validated['contact_id'])) {
                        throw new \Exception('Customer MUST be selected to use Store Credit.');
                    }
                    
                    $wallet = DB::table('customer_wallets')
                        ->where('contact_id', $validated['contact_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$wallet || \App\Modules\Sales\Services\FinancialCalculator::of($wallet->balance)->isLessThan($amountPaid)) {
                        throw new \Exception('Location Overdraft: Insufficient store credit balance.');
                    }

                    DB::table('customer_wallets')
                        ->where('id', $wallet->id)
                        ->decrement('balance', (string)$amountPaid);
                }

                $paymentStatus = 'paid';
                if (!$isPosting || $amountDue->isGreaterThan(0.01)) {
                    $paymentStatus = $amountPaid->isGreaterThan(0) && $isPosting ? 'partial' : 'due';
                }

                $afterDiscount = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($subtotal, $discountValue);

                $insertData = [
                    'business_id' => $businessId,
                    'location_id' => $validated['location_id'],
                    'created_by' => $request->user()->id,
                    'type' => 'sell',
                    'status' => !$isPosting ? 'draft' : 'final',
                    'is_quotation' => !$isPosting,
                    'document_type' => $documentType,
                    'invoice_no' => $invoiceNo,
                    'transaction_date' => Carbon::now(),
                    'total_before_tax' => (string) $afterDiscount,
                    'tax_amount' => (string) $taxAmount,
                    'discount_amount' => (string) $discountValue,
                    'discount_type' => $validated['discount_type'] ?? null,
                    'final_total' => (string) $finalTotal,
                    'amount_due' => (string) $amountDue,
                    'payment_status' => $paymentStatus,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                $transactionId = DB::table('transactions')->insertGetId($insertData);

                $productQuantities = [];
                foreach ($validated['items'] as $item) {
                    $fractionalRatio = $item['fractional_ratio'] ?? 1.0;
                    $actualQty = $item['quantity'] * $fractionalRatio;
                    
                    if (!isset($productQuantities[$item['product_id']])) {
                        $productQuantities[$item['product_id']] = 0;
                    }
                    $productQuantities[$item['product_id']] += $actualQty;
                }
                
                $batchFifoAction = app(\App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction::class);
                $cogsMap = !$isPosting ? [] : $batchFifoAction->execute($businessId, $productQuantities);

                $totalCogs = \App\Modules\Sales\Services\FinancialCalculator::of(0);

                foreach ($validated['items'] as $item) {
                    $fractionalRatio = $item['fractional_ratio'] ?? 1.0;
                    $actualQty = $item['quantity'] * $fractionalRatio;

                    $lineId = DB::table('transaction_lines')->insertGetId([
                        'transaction_id' => $transactionId,
                        'product_id' => $item['product_id'],
                        'quantity' => $actualQty,
                        'unit_price' => $item['price'],
                        'unit_price_inc_tax' => (string) \App\Modules\Sales\Services\FinancialCalculator::add($item['price'], \App\Modules\Sales\Services\FinancialCalculator::calculateTax($item['price'], $validated['tax_rate'])),
                        'item_tax' => (string) \App\Modules\Sales\Services\FinancialCalculator::calculateTax($item['price'], $validated['tax_rate']),
                        'dosage_instructions' => $item['dosage_instructions'] ?? null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    if ($isPosting) {
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

                        $qtyBeforeStr = (string) \App\Modules\Sales\Services\FinancialCalculator::of($stock->qty_available);
                        $qtyAfterStr = (string) \App\Modules\Sales\Services\FinancialCalculator::subtract($stock->qty_available, $actualQty);

                        DB::table('product_stocks')->where('id', $stock->id)
                            ->update(['qty_available' => $qtyAfterStr, 'updated_at' => Carbon::now()]);

                        $auditService = app(\App\Modules\Security\Services\ForensicAuditService::class);
                        $auditService->snapshot(
                            'ProductStock',
                            $stock->id,
                            'sale_checkout',
                            'deduct_stock',
                            ['qty_available' => $qtyBeforeStr],
                            ['qty_available' => $qtyAfterStr],
                            request()->path()
                        );
                    }
                    
                    if ($isPosting && !empty($item['serial_numbers'])) {
                        if (count($item['serial_numbers']) !== (int)$item['quantity']) {
                            throw new \Exception('Number of selected serials (' . count($item['serial_numbers']) . ') does not match cart quantity (' . $item['quantity'] . ') for product ID ' . $item['product_id']);
                        }

                        // Ghost Serial / Double-Sale Protection
                        $exists = DB::table('transaction_item_serials')
                            ->whereIn('serial_number', $item['serial_numbers'])
                            ->exists();

                        if ($exists) {
                            return response()->json(['message' => 'FPM Security: Serial/IMEI number already exists in an active asset ledger.'], 422)->throwResponse();
                        }

                        foreach ($item['serial_numbers'] as $serial) {
                            DB::table('transaction_item_serials')->insert([
                                'transaction_item_id' => $lineId,
                                'serial_number' => $serial,
                                'imei_number' => null,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
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
                
                // Aggregate total exact COGS from batch map
                if (!$isQuotation) {
                    foreach ($cogsMap as $cogs) {
                        $totalCogs = $totalCogs->plus(\App\Modules\Sales\Services\FinancialCalculator::of($cogs));
                    }
                }
                
                DB::table('transaction_lines')->insert($lines);

                // 3. Insert Payment
                if (!$isQuotation && $amountPaid->isGreaterThan(0)) {
                    DB::table('transaction_payments')->insert([
                        'transaction_id' => $transactionId,
                        'amount' => (string) $amountPaid,
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

                // 4. Double-Entry Ledger Hook
                if (!$isQuotation) {
                    $cashAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH);
                    $receivableAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::AR);
                    $salesAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::SALES);
                    $taxAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::TAX_PAYABLE);
                    $discountAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::DISCOUNT);
                    
                    // COGS & Inventory Accounts
                    $cogsAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::COGS);
                    $inventoryAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);

                    $debits = [];
                    $credits = [];

                    // Credit: Sales Revenue (Subtotal)
                    $credits[] = ['chart_of_account_id' => $salesAccountId, 'amount' => (string) $subtotal];

                    // Credit: Tax Payable
                    if ($taxAmount->isGreaterThan(0)) {
                        $credits[] = ['chart_of_account_id' => $taxAccountId, 'amount' => (string) $taxAmount];
                    }

                    // Debit: Discount Expense
                    if ($discountValue->isGreaterThan(0)) {
                        $debits[] = ['chart_of_account_id' => $discountAccountId, 'amount' => (string) $discountValue];
                    }

                    // Debit: Cash (Amount Paid)
                    if ($amountPaid->isGreaterThan(0)) {
                        $debits[] = ['chart_of_account_id' => $cashAccountId, 'amount' => (string) $amountPaid];
                    }

                    // Debit: Accounts Receivable (Amount Due)
                    if ($amountDue->isGreaterThan(0)) {
                        $debits[] = ['chart_of_account_id' => $receivableAccountId, 'amount' => (string) $amountDue];
                    }

                    // Strict FIFO COGS Journal Posting
                    if ($totalCogs->isGreaterThan(0)) {
                        $debits[] = ['chart_of_account_id' => $cogsAccountId, 'amount' => (string) $totalCogs];
                        $credits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $totalCogs];
                    }

                    $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);
                    $ledger->recordEntry(
                        $businessId,
                        $invoiceNo,
                        Carbon::now()->toDateString(),
                        "Sale #$invoiceNo",
                        $debits,
                        $credits,
                        $transactionId,
                        'transaction',
                        $request->user()->id
                    );
                }

                // 5. Loyalty Point Earn
                if ($isPosting && !empty($validated['contact_id']) && $finalTotal->isGreaterThan(0)) {
                    $pointsEarned = floor((float)$finalTotal->getValue() / 100);
                    if ($pointsEarned > 0) {
                        $lastLedger = DB::table('loyalty_point_ledgers')->where('contact_id', $validated['contact_id'])->orderByDesc('id')->first();
                        $runningBalance = ($lastLedger->running_balance ?? 0) + $pointsEarned;
                        DB::table('loyalty_point_ledgers')->insert([
                            'business_id' => $businessId,
                            'contact_id' => $validated['contact_id'],
                            'transaction_id' => $transactionId,
                            'points_earned' => $pointsEarned,
                            'points_redeemed' => 0,
                            'running_balance' => $runningBalance,
                            'description' => 'Points earned on Sale #'.$invoiceNo,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }

                return $transactionId;
            }, 5);
            
            \Illuminate\Support\Facades\Cache::store('redis')->forget("dashboard_kpis_business_{$businessId}");

            // Dispatch Omnichannel Notification / Digital Receipt
            if (!$isQuotation) {
                $contact = null;
                if (!empty($validated['contact_id'])) {
                    $contact = DB::table('contacts')->where('id', $validated['contact_id'])->first();
                }

                if ($contact) {
                    $notifyMethods = [];
                    if (!empty($contact->email)) $notifyMethods[] = 'email';
                    if (!empty($contact->mobile)) $notifyMethods[] = 'whatsapp';

                    if (!empty($notifyMethods)) {
                        \App\Modules\Sales\Jobs\SendInvoiceNotificationJob::dispatch($result, $businessId, $contact, $notifyMethods);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Sale processed successfully',
                'transaction_id' => $transactionId,
                'invoice_no' => $invoiceNo,
                'subtotal' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($subtotal),
                'discount' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($discountValue),
                'tax' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($taxAmount),
                'final_total' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($finalTotal),
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

    public function convertToInvoice(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $transaction = DB::table('transactions')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'converted' || $transaction->document_type === 'Invoice') {
            return response()->json(['message' => 'Document is already an active invoice or converted.'], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz',
            'amount_paid' => 'required|numeric|min:0',
            'serials' => 'nullable|array',
            'serials.*.product_id' => 'required|integer',
            'serials.*.serial_numbers' => 'required|array',
            'serials.*.serial_numbers.*' => 'string'
        ]);

        try {
            $newTransactionId = DB::transaction(function () use ($transaction, $businessId, $request, $validated) {
                // We will create a fresh POSTING invoice mimicking the quotation.
                $invoiceNo = 'INV-' . time() . '-' . mt_rand(100, 999);
                $finalTotal = \App\Modules\Sales\Services\FinancialCalculator::of($transaction->final_total);
                $amountPaid = \App\Modules\Sales\Services\FinancialCalculator::of($validated['amount_paid']);
                $amountDue = $finalTotal->subtract($amountPaid);
                $paymentStatus = $amountDue->isGreaterThan(0.01) ? ($amountPaid->isGreaterThan(0) ? 'partial' : 'due') : 'paid';

                $insertData = [
                    'business_id' => $businessId,
                    'location_id' => $transaction->location_id,
                    'created_by' => $request->user()->id,
                    'type' => 'sell',
                    'status' => 'final',
                    'is_quotation' => false,
                    'document_type' => 'Invoice',
                    'invoice_no' => $invoiceNo,
                    'transaction_date' => Carbon::now(),
                    'total_before_tax' => $transaction->total_before_tax,
                    'tax_amount' => $transaction->tax_amount,
                    'discount_amount' => $transaction->discount_amount,
                    'discount_type' => $transaction->discount_type,
                    'final_total' => $transaction->final_total,
                    'amount_due' => (string) $amountDue,
                    'payment_status' => $paymentStatus,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                if ($transaction->contact_id) {
                    $insertData['contact_id'] = $transaction->contact_id;
                }

                $newTxId = DB::table('transactions')->insertGetId($insertData);

                // Clone lines and handle inventory + serials
                $oldLines = DB::table('transaction_lines')->where('transaction_id', $transaction->id)->get();
                $productQuantities = [];
                foreach ($oldLines as $line) {
                    if (!isset($productQuantities[$line->product_id])) {
                        $productQuantities[$line->product_id] = 0;
                    }
                    $productQuantities[$line->product_id] += $line->quantity;
                }

                $batchFifoAction = app(\App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction::class);
                $cogsMap = $batchFifoAction->execute($businessId, $productQuantities);

                $totalCogs = \App\Modules\Sales\Services\FinancialCalculator::of(0);

                foreach ($oldLines as $line) {
                    $lineId = DB::table('transaction_lines')->insertGetId([
                        'transaction_id' => $newTxId,
                        'product_id' => $line->product_id,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unit_price,
                        'unit_price_inc_tax' => $line->unit_price_inc_tax,
                        'item_tax' => $line->item_tax,
                        'dosage_instructions' => $line->dosage_instructions,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    $stock = DB::table('product_stocks')
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $transaction->location_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || $stock->qty_available < $line->quantity) {
                        throw new \Exception('Insufficient stock for product ID ' . $line->product_id);
                    }

                    DB::table('product_stocks')->where('id', $stock->id)
                        ->decrement('qty_available', $line->quantity);

                    // Check Serials
                    if (!empty($validated['serials'])) {
                        $serialMap = collect($validated['serials'])->firstWhere('product_id', $line->product_id);
                        if ($serialMap && !empty($serialMap['serial_numbers'])) {
                            if (count($serialMap['serial_numbers']) !== (int)$line->quantity) {
                                throw new \Exception('Serial count mismatch for product ID ' . $line->product_id);
                            }

                            $exists = DB::table('transaction_item_serials')
                                ->whereIn('serial_number', $serialMap['serial_numbers'])
                                ->exists();

                            if ($exists) {
                                throw new \Exception('FPM Security: Serial/IMEI number already exists in an active asset ledger.');
                            }

                            foreach ($serialMap['serial_numbers'] as $serial) {
                                DB::table('transaction_item_serials')->insert([
                                    'transaction_item_id' => $lineId,
                                    'serial_number' => $serial,
                                    'imei_number' => null,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ]);
                            }

                            DB::table('product_serials')
                                ->where('business_id', $businessId)
                                ->where('product_id', $line->product_id)
                                ->whereIn('serial_number', $serialMap['serial_numbers'])
                                ->update(['status' => 'sold', 'transaction_id' => $newTxId]);
                        }
                    }
                }

                foreach ($cogsMap as $cogs) {
                    $totalCogs = $totalCogs->plus(\App\Modules\Sales\Services\FinancialCalculator::of($cogs));
                }

                if ($amountPaid->isGreaterThan(0)) {
                    DB::table('transaction_payments')->insert([
                        'transaction_id' => $newTxId,
                        'amount' => (string) $amountPaid,
                        'method' => $validated['payment_method'],
                        'paid_on' => Carbon::now(),
                        'created_by' => $request->user()->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                // Double Entry
                $cashAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH);
                $receivableAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::AR);
                $salesAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::SALES);
                $taxAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::TAX_PAYABLE);
                $discountAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::DISCOUNT);
                $cogsAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::COGS);
                $inventoryAccountId = \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);

                $debits = [];
                $credits = [];

                $subtotalCalc = \App\Modules\Sales\Services\FinancialCalculator::of($transaction->total_before_tax);
                $taxCalc = \App\Modules\Sales\Services\FinancialCalculator::of($transaction->tax_amount);
                $discountCalc = \App\Modules\Sales\Services\FinancialCalculator::of($transaction->discount_amount);

                $credits[] = ['chart_of_account_id' => $salesAccountId, 'amount' => (string) $subtotalCalc->add($discountCalc)];

                if ($taxCalc->isGreaterThan(0)) {
                    $credits[] = ['chart_of_account_id' => $taxAccountId, 'amount' => (string) $taxCalc];
                }

                if ($discountCalc->isGreaterThan(0)) {
                    $debits[] = ['chart_of_account_id' => $discountAccountId, 'amount' => (string) $discountCalc];
                }

                if ($amountPaid->isGreaterThan(0)) {
                    $debits[] = ['chart_of_account_id' => $cashAccountId, 'amount' => (string) $amountPaid];
                }

                if ($amountDue->isGreaterThan(0)) {
                    $debits[] = ['chart_of_account_id' => $receivableAccountId, 'amount' => (string) $amountDue];
                }

                if ($totalCogs->isGreaterThan(0)) {
                    $debits[] = ['chart_of_account_id' => $cogsAccountId, 'amount' => (string) $totalCogs];
                    $credits[] = ['chart_of_account_id' => $inventoryAccountId, 'amount' => (string) $totalCogs];
                }

                $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);
                $ledger->recordEntry(
                    $businessId,
                    $invoiceNo,
                    Carbon::now()->toDateString(),
                    "Sale #$invoiceNo",
                    $debits,
                    $credits,
                    $newTxId,
                    'transaction',
                    $request->user()->id
                );

                // Mark original as converted
                DB::table('transactions')->where('id', $transaction->id)->update(['status' => 'converted']);

                return $newTxId;
            }, 5);

            return response()->json(['message' => 'Successfully converted to Invoice', 'transaction_id' => $newTransactionId], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Conversion failed', 'error' => $e->getMessage()], 500);
        }
    }
}
