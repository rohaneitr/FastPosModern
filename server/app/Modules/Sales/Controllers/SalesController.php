<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    // ─────────────────────────────────────────────
    //  SALES LIST (with filters)
    // ─────────────────────────────────────────────
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $status     = $request->query('status');      // final|draft|quotation|sell_return
        $search     = $request->query('search');
        $startDate  = $request->query('start_date');
        $endDate    = $request->query('end_date');
        $perPage    = min((int)$request->query('per_page', 20), 100);

        $query = DB::table('transactions')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->leftJoin('users',    'transactions.created_by', '=', 'users.id')
            ->tenant('transactions')
            ->where('transactions.type', 'sell');

        if ($status === 'quotation') {
            $query->where('transactions.is_quotation', true);
        } elseif ($status === 'sell_return') {
            $query->where('transactions.type', 'sell_return');
        } elseif ($status) {
            $query->where('transactions.status', $status)->where('transactions.is_quotation', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transactions.invoice_no', 'like', "%$search%")
                  ->orWhere('contacts.name', 'like', "%$search%");
            });
        }

        if ($startDate) $query->whereDate('transactions.transaction_date', '>=', $startDate);
        if ($endDate)   $query->whereDate('transactions.transaction_date', '<=', $endDate);

        $sales = $query->select(
            'transactions.*',
            'contacts.name as customer_name',
            DB::raw("CONCAT(COALESCE(users.first_name,''), ' ', COALESCE(users.last_name,'')) as cashier_name")
        )
        ->orderBy('transactions.transaction_date', 'desc')
        ->paginate($perPage);

        return response()->json($sales);
    }

    // ─────────────────────────────────────────────
    //  SINGLE SALE DETAILS (with lines + payments)
    // ─────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $transaction = DB::table('transactions')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->where('transactions.id', $id)
            ->tenant('transactions')
            ->select('transactions.*', 'contacts.name as customer_name', 'contacts.mobile as customer_mobile', 'contacts.email as customer_email')
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        $lines = DB::table('transaction_lines')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transaction_lines.transaction_id', $id)
            ->select('transaction_lines.*', 'products.name as product_name', 'products.sku')
            ->get();

        $payments = DB::table('transaction_payments')
            ->where('transaction_id', $id)
            ->get();

        $transaction->lines    = $lines;
        $transaction->payments = $payments;

        return response()->json($transaction);
    }

    // ─────────────────────────────────────────────
    //  CREATE SALE (manual sale from dashboard)
    // ─────────────────────────────────────────────
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id'      => ['required', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'contact_id'       => ['nullable', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'transaction_date' => 'required|date',
            'status'           => 'required|in:final,draft',
            'is_quotation'     => 'boolean',
            'tax_rate'         => 'nullable|numeric|min:0',
            'discount_type'    => 'nullable|in:fixed,percentage',
            'discount_amount'  => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|string|in:cash,card,bank_transfer,bkash,sslcommerz',
            'amount_paid'      => 'nullable|numeric|min:0',
            'note'             => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.product_id'  => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount'    => 'nullable|numeric|min:0',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $businessId, $request) {
                $subtotal = \App\Modules\Sales\Services\FinancialCalculator::of(0);
                foreach ($validated['items'] as &$item) {
                    // ZERO TRUST: Strip frontend price payload and fetch true price from database
                    $variationQuery = DB::table('variations')->where('product_id', $item['product_id']);
                    if (!empty($item['variation_id'])) {
                        $variationQuery->where('id', $item['variation_id']);
                    }
                    $variation = $variationQuery->first();
                    
                    if (!$variation) {
                        throw new \Exception('Invalid product variation for ID: ' . $item['product_id']);
                    }
                    
                    // Override the price
                    $item['unit_price'] = $variation->sell_price_inc_tax ?? 0;

                    $lineTotal = \App\Modules\Sales\Services\FinancialCalculator::calculateLineTotal($item['unit_price'], $item['quantity']);
                    $lineDisc = \App\Modules\Sales\Services\FinancialCalculator::of($item['discount'] ?? 0);
                    $subtotal = $subtotal->plus(\App\Modules\Sales\Services\FinancialCalculator::applyDiscount($lineTotal, $lineDisc));
                }
                unset($item); // break reference

                $taxRate = $validated['tax_rate'] ?? 0;
                $discValue = \App\Modules\Sales\Services\FinancialCalculator::of(0);
                if (!empty($validated['discount_type']) && !empty($validated['discount_amount'])) {
                    if ($validated['discount_type'] === 'percentage') {
                        $discValue = \App\Modules\Sales\Services\FinancialCalculator::calculatePercentageDiscount($subtotal, $validated['discount_amount']);
                    } else {
                        $discValue = \App\Modules\Sales\Services\FinancialCalculator::of($validated['discount_amount']);
                        if ($discValue->isGreaterThan($subtotal)) {
                            $discValue = clone $subtotal;
                        }
                    }
                }

                $afterDisc  = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($subtotal, $discValue);
                $taxAmount  = \App\Modules\Sales\Services\FinancialCalculator::calculateTax($afterDisc, $taxRate);
                $finalTotal = $afterDisc->plus($taxAmount);
                $amtPaid    = \App\Modules\Sales\Services\FinancialCalculator::of($validated['amount_paid'] ?? 0);
                $payStatus  = $amtPaid->isGreaterThanOrEqualTo($finalTotal) ? 'paid' : ($amtPaid->isGreaterThan(0) ? 'partial' : 'due');

                $prefix     = ($validated['is_quotation'] ?? false) ? 'QUO' : (($validated['status'] === 'draft') ? 'DRF' : 'INV');
                $invoiceNo  = $prefix . '-' . time() . '-' . mt_rand(100, 999);

                $txId = DB::table('transactions')->insertGetId([
                    'business_id'     => $businessId,
                    'location_id'     => $validated['location_id'],
                    'contact_id'      => $validated['contact_id'] ?? null,
                    'created_by'      => $request->user()->id,
                    'type'            => 'sell',
                    'status'          => $validated['status'],
                    'is_quotation'    => $validated['is_quotation'] ?? false,
                    'invoice_no'      => $invoiceNo,
                    'transaction_date'=> $validated['transaction_date'],
                    'total_before_tax'=> (string) $afterDisc,
                    'tax_amount'      => (string) $taxAmount,
                    'discount_amount' => (string) $discValue,
                    'discount_type'   => $validated['discount_type'] ?? null,
                    'final_total'     => (string) $finalTotal,
                    'amount_due'      => (string) \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($finalTotal, $amtPaid),
                    'payment_status'  => $payStatus,
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ]);

                $lines = [];
                foreach ($validated['items'] as $item) {
                    // Unit price has already been overridden by Zero-Trust DB fetch
                    $lineTotal = $item['unit_price'] * $item['quantity'];
                    $lineDisc  = $item['discount'] ?? 0;
                    $lines[] = [
                        'transaction_id'    => $txId,
                        'product_id'        => $item['product_id'],
                        'variation_id'      => $item['variation_id'] ?? null,
                        'quantity'          => $item['quantity'],
                        'unit_price'        => $item['unit_price'],
                        'unit_price_inc_tax'=> (string) \App\Modules\Sales\Services\FinancialCalculator::add($item['unit_price'], \App\Modules\Sales\Services\FinancialCalculator::calculateTax($item['unit_price'], $taxRate)),
                        'item_tax'          => (string) \App\Modules\Sales\Services\FinancialCalculator::calculateTax($item['unit_price'], $taxRate),
                        'created_at'        => Carbon::now(),
                        'updated_at'        => Carbon::now(),
                    ];

                    // Only decrement stock for final sales
                    if ($validated['status'] === 'final' && !($validated['is_quotation'] ?? false)) {
                        $stock = DB::table('product_stocks')
                            ->where('product_id', $item['product_id'])
                            ->where('location_id', $validated['location_id'])
                            ->lockForUpdate()->first();

                        if (!$stock || $stock->qty_available < $item['quantity']) {
                            throw new \Exception('Insufficient stock for product ID: ' . $item['product_id']);
                        }
                        DB::table('product_stocks')->where('id', $stock->id)->decrement('qty_available', $item['quantity']);
                    }
                }
                DB::table('transaction_lines')->insert($lines);

                // Insert payment if amount paid > 0
                if ($amtPaid->isGreaterThan(0) && !empty($validated['payment_method'])) {
                    DB::table('transaction_payments')->insert([
                        'transaction_id' => $txId,
                        'amount'         => (string) $amtPaid,
                        'method'         => $validated['payment_method'],
                        'paid_on'        => Carbon::now(),
                        'created_by'     => $request->user()->id,
                        'created_at'     => Carbon::now(),
                        'updated_at'     => Carbon::now(),
                    ]);
                }

                return [
                    'txId' => $txId,
                    'invoiceNo' => $invoiceNo,
                    'finalTotal' => $finalTotal,
                    'payStatus' => $payStatus,
                ];
            }, 5);

            if ($validated['status'] === 'final' && !($validated['is_quotation'] ?? false)) {
                \Illuminate\Support\Facades\Cache::forget("dashboard_kpis_business_{$businessId}");
            }

            return response()->json([
                'message'        => 'Sale created successfully',
                'transaction_id' => $result['txId'],
                'invoice_no'     => $result['invoiceNo'],
                'final_total'    => \App\Modules\Sales\Services\FinancialCalculator::toFloat($result['finalTotal']),
                'payment_status' => $result['payStatus'],
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create sale', 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  UPDATE SALE (edit draft/quotation)
    // ─────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $tx = DB::table('transactions')
            ->where('id', $id)->tenant()->first();

        if (!$tx) return response()->json(['message' => 'Sale not found'], 404);
        if ($tx->status === 'final' && !$tx->is_quotation) {
            return response()->json(['message' => 'Final sales cannot be edited. Use sell return instead.'], 422);
        }

        $validated = $request->validate([
            'contact_id'       => ['nullable', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'transaction_date' => 'required|date',
            'note'             => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.product_id'  => ['required', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = \App\Modules\Sales\Services\FinancialCalculator::of(0);
            foreach ($validated['items'] as &$item) {
                // ZERO TRUST: Strip frontend price payload and fetch true price from database
                $variationQuery = DB::table('variations')->where('product_id', $item['product_id']);
                if (!empty($item['variation_id'])) {
                    $variationQuery->where('id', $item['variation_id']);
                }
                $variation = $variationQuery->first();
                
                if (!$variation) {
                    throw new \Exception('Invalid product variation for ID: ' . $item['product_id']);
                }
                
                // Override the price
                $item['unit_price'] = $variation->sell_price_inc_tax ?? 0;

                $lineTotal = \App\Modules\Sales\Services\FinancialCalculator::calculateLineTotal($item['unit_price'], $item['quantity']);
                $subtotal = $subtotal->plus($lineTotal);
            }
            unset($item); // break reference
            
            $taxAmount  = \App\Modules\Sales\Services\FinancialCalculator::calculateTax($subtotal, $tx->tax_rate ?? 0);
            $finalTotal = $subtotal->plus($taxAmount);

            DB::table('transactions')->where('id', $id)->update([
                'contact_id'       => $validated['contact_id'] ?? $tx->contact_id,
                'transaction_date' => $validated['transaction_date'],
                'total_before_tax' => (string) $subtotal,
                'tax_amount'       => (string) $taxAmount,
                'final_total'      => (string) $finalTotal,
                'updated_at'       => Carbon::now(),
            ]);

            DB::table('transaction_lines')->where('transaction_id', $id)->delete();

            $lines = [];
            foreach ($validated['items'] as $item) {
                $itemTax = \App\Modules\Sales\Services\FinancialCalculator::calculateTax($item['unit_price'], $tx->tax_rate ?? 0);
                $lines[] = [
                    'transaction_id'    => $id,
                    'product_id'        => $item['product_id'],
                    'variation_id'      => $item['variation_id'] ?? null,
                    'quantity'          => $item['quantity'],
                    'unit_price'        => $item['unit_price'],
                    'unit_price_inc_tax'=> (string) \App\Modules\Sales\Services\FinancialCalculator::add($item['unit_price'], $itemTax),
                    'item_tax'          => (string) $itemTax,
                    'created_at'        => Carbon::now(),
                    'updated_at'        => Carbon::now(),
                ];
            }
            DB::table('transaction_lines')->insert($lines);

            DB::commit();
            return response()->json(['message' => 'Sale updated successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  DELETE DRAFT / QUOTATION
    // ─────────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $tx = DB::table('transactions')
            ->where('id', $id)->tenant()->first();

        if (!$tx) return response()->json(['message' => 'Sale not found'], 404);

        if ($tx->status === 'final' && !$tx->is_quotation) {
            return response()->json(['message' => 'Cannot delete a finalised sale. Use sell return.'], 422);
        }

        DB::table('transaction_lines')->where('transaction_id', $id)->delete();
        DB::table('transaction_payments')->where('transaction_id', $id)->delete();
        DB::table('transactions')->where('id', $id)->delete();

        return response()->json(['message' => 'Sale deleted']);
    }

    // ─────────────────────────────────────────────
    //  CONVERT QUOTATION / DRAFT → FINAL SALE
    // ─────────────────────────────────────────────
    public function convertToSale(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $tx = DB::table('transactions')
            ->where('id', $id)->tenant()->first();

        if (!$tx) return response()->json(['message' => 'Sale not found'], 404);
        if ($tx->status === 'final' && !$tx->is_quotation) {
            return response()->json(['message' => 'Already a final sale'], 422);
        }

        try {
            DB::beginTransaction();

            $invoiceNo = 'INV-' . time() . '-' . mt_rand(100, 999);
            DB::table('transactions')->where('id', $id)->update([
                'status'       => 'final',
                'is_quotation' => false,
                'invoice_no'   => $invoiceNo,
                'updated_at'   => Carbon::now(),
            ]);

            // Decrement stock
            $lines = DB::table('transaction_lines')->where('transaction_id', $id)->get();
            foreach ($lines as $line) {
                $stock = DB::table('product_stocks')
                    ->where('product_id', $line->product_id)
                    ->where('location_id', $tx->location_id)
                    ->lockForUpdate()->first();

                if (!$stock || $stock->qty_available < $line->quantity) {
                    throw new \Exception('Insufficient stock for product ID: ' . $line->product_id);
                }
                DB::table('product_stocks')->where('id', $stock->id)->decrement('qty_available', $line->quantity);
            }

            DB::commit();
            \Illuminate\Support\Facades\Cache::forget("dashboard_kpis_business_{$businessId}");
            
            return response()->json(['message' => 'Converted to sale', 'invoice_no' => $invoiceNo]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  ADD PAYMENT TO EXISTING SALE
    // ─────────────────────────────────────────────
    public function addPayment(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $tx = DB::table('transactions')
            ->where('id', $id)->tenant()->first();

        if (!$tx) return response()->json(['message' => 'Sale not found'], 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz,advance',
            'note'   => 'nullable|string',
        ]);

        $totalPaid = DB::table('transaction_payments')->where('transaction_id', $id)->sum('amount');
        $remaining = \App\Modules\Sales\Services\FinancialCalculator::subtract($tx->final_total, $totalPaid);

        if (\App\Modules\Sales\Services\FinancialCalculator::of($validated['amount'])->isGreaterThan($remaining->plus(0.01))) {
            return response()->json(['message' => "Payment of {$validated['amount']} exceeds remaining balance of {$remaining}"], 422);
        }

        DB::table('transaction_payments')->insert([
            'transaction_id' => $id,
            'amount'         => $validated['amount'],
            'method'         => $validated['method'],
            'note'           => $validated['note'] ?? null,
            'paid_on'        => Carbon::now(),
            'created_by'     => $request->user()->id,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);

        $newTotalPaid = \App\Modules\Sales\Services\FinancialCalculator::add($totalPaid, $validated['amount']);
        $payStatus    = $newTotalPaid->isGreaterThanOrEqualTo($tx->final_total) ? 'paid' : 'partial';
        $amountDue    = \App\Modules\Sales\Services\FinancialCalculator::applyDiscount($tx->final_total, $newTotalPaid);
        
        DB::table('transactions')->where('id', $id)->update([
            'payment_status' => $payStatus,
            'amount_due'     => (string) $amountDue,
            'updated_at'     => Carbon::now(),
        ]);

        return response()->json(['message' => 'Payment recorded', 'payment_status' => $payStatus, 'amount_due' => \App\Modules\Sales\Services\FinancialCalculator::toFloat($amountDue)]);
    }

    // ─────────────────────────────────────────────
    //  SHIPMENT: CREATE / UPDATE
    // ─────────────────────────────────────────────
    public function createShipment(Request $request, $id)
    {
        $businessId = $request->user()->business_id;
        $tx = DB::table('transactions')->where('id', $id)->tenant()->first();
        if (!$tx) return response()->json(['message' => 'Sale not found'], 404);

        $validated = $request->validate([
            'shipping_address'  => 'required|string|max:500',
            'shipping_status'   => 'required|in:pending,shipped,in_transit,delivered,failed',
            'tracking_number'   => 'nullable|string',
            'estimated_delivery'=> 'nullable|date',
            'note'              => 'nullable|string',
        ]);

        $exists = DB::table('shipments')->where('transaction_id', $id)->first();

        if ($exists) {
            DB::table('shipments')->where('transaction_id', $id)->update(array_merge($validated, ['updated_at' => Carbon::now()]));
        } else {
            DB::table('shipments')->insert(array_merge($validated, [
                'transaction_id' => $id,
                'business_id'    => $businessId,
                'created_by'     => $request->user()->id,
                'created_at'     => Carbon::now(),
                'updated_at'     => Carbon::now(),
            ]));
        }

        return response()->json(['message' => 'Shipment saved']);
    }

    public function getShipment(Request $request, $id)
    {
        $businessId = $request->user()->business_id;
        $tx = DB::table('transactions')->where('id', $id)->tenant()->first();
        if (!$tx) return response()->json(['message' => 'Sale not found'], 404);

        $shipment = DB::table('shipments')->where('transaction_id', $id)->first();
        return response()->json($shipment);
    }

    // ─────────────────────────────────────────────
    //  PAYMENT HISTORY LIST
    // ─────────────────────────────────────────────
    public function payments(Request $request)
    {
        $businessId = $request->user()->business_id;
        $perPage    = min((int)$request->query('per_page', 20), 100);

        $payments = DB::table('transaction_payments')
            ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->tenant('transactions')
            ->select(
                'transaction_payments.*',
                'transactions.invoice_no',
                'transactions.final_total',
                'contacts.name as customer_name'
            )
            ->orderByDesc('transaction_payments.paid_on')
            ->paginate($perPage);

        return response()->json($payments);
    }
}

