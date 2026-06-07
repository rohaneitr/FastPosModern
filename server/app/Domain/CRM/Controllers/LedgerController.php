<?php

namespace App\Domain\CRM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LedgerController extends Controller
{
    /**
     * Get the ledger transactions and payments for a specific contact.
     */
    public function index(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $contact = DB::table('contacts')->where('id', $id)->where('business_id', $businessId)->first();
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // 1. Sales (Debits)
        $sales = DB::table('transactions')
            ->where('contact_id', $id)
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->select(
                'id as ref_id',
                'transaction_date as date',
                DB::raw("'sale' as type"),
                'invoice_no as description',
                'final_total as debit',
                DB::raw('0 as credit')
            );

        // 2. Returns (Credits)
        $returns = DB::table('transactions')
            ->where('contact_id', $id)
            ->where('business_id', $businessId)
            ->where('type', 'sell_return')
            ->where('status', 'final')
            ->select(
                'id as ref_id',
                'transaction_date as date',
                DB::raw("'return' as type"),
                DB::raw("'Sell Return' as description"),
                DB::raw('0 as debit'),
                'final_total as credit'
            );

        // 3. Payments (Credits)
        $payments = DB::table('transaction_payments')
            ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.contact_id', $id)
            ->where('transactions.business_id', $businessId)
            ->where('transaction_payments.method', '!=', 'advance')
            ->select(
                'transaction_payments.id as ref_id',
                'transaction_payments.paid_on as date',
                DB::raw("'payment' as type"),
                DB::raw("CONCAT('Payment via ', transaction_payments.method) as description"),
                DB::raw('0 as debit'),
                'transaction_payments.amount as credit'
            );

        // Apply date filters if provided
        if ($startDate) {
            $sales->whereDate('transaction_date', '>=', $startDate);
            $returns->whereDate('transaction_date', '>=', $startDate);
            $payments->whereDate('transaction_payments.paid_on', '>=', $startDate);
        }
        if ($endDate) {
            $sales->whereDate('transaction_date', '<=', $endDate);
            $returns->whereDate('transaction_date', '<=', $endDate);
            $payments->whereDate('transaction_payments.paid_on', '<=', $endDate);
        }

        // Union all queries
        $ledger = $sales->unionAll($returns)->unionAll($payments)
            ->orderBy('date', 'asc')
            ->get();

        // Calculate opening balance before start_date if start_date is provided
        $openingBalance = $contact->opening_balance ?? 0;
        
        if ($startDate) {
            $pastSales = DB::table('transactions')
                ->where('contact_id', $id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereDate('transaction_date', '<', $startDate)
                ->sum('final_total');

            $pastReturns = DB::table('transactions')
                ->where('contact_id', $id)
                ->where('type', 'sell_return')
                ->where('status', 'final')
                ->whereDate('transaction_date', '<', $startDate)
                ->sum('final_total');

            $pastPayments = DB::table('transaction_payments')
                ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
                ->where('transactions.contact_id', $id)
                ->where('transaction_payments.method', '!=', 'advance')
                ->whereDate('transaction_payments.paid_on', '<', $startDate)
                ->sum('transaction_payments.amount');

            $openingBalance += $pastSales - $pastReturns - $pastPayments;
        }

        // Compute running balance
        $runningBalance = $openingBalance;
        $ledgerWithBalance = [];

        // Add opening balance row
        $ledgerWithBalance[] = [
            'ref_id' => 'ob',
            'date' => $startDate ? Carbon::parse($startDate)->startOfDay()->toDateTimeString() : null,
            'type' => 'opening_balance',
            'description' => 'Opening Balance',
            'debit' => $openingBalance > 0 ? $openingBalance : 0,
            'credit' => $openingBalance < 0 ? abs($openingBalance) : 0,
            'balance' => $runningBalance
        ];

        foreach ($ledger as $entry) {
            $runningBalance += $entry->debit;
            $runningBalance -= $entry->credit;
            
            $ledgerWithBalance[] = [
                'ref_id' => $entry->ref_id,
                'date' => $entry->date,
                'type' => $entry->type,
                'description' => $entry->description,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
                'balance' => $runningBalance
            ];
        }

        // Reverse to show latest first if we want, but usually ledgers are chronological.
        // The frontend can handle sorting. We will return ascending.
        return response()->json([
            'contact' => $contact,
            'ledger' => array_reverse($ledgerWithBalance), // Return newest first for standard view
            'closing_balance' => $runningBalance
        ]);
    }

    /**
     * Get summary metrics for the customer profile.
     */
    public function summary(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $contact = DB::table('contacts')->where('id', $id)->where('business_id', $businessId)->first();
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $totalSales = DB::table('transactions')
            ->where('contact_id', $id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->sum('final_total');

        $totalReturns = DB::table('transactions')
            ->where('contact_id', $id)
            ->where('type', 'sell_return')
            ->where('status', 'final')
            ->sum('final_total');

        $totalPayments = DB::table('transaction_payments')
            ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.contact_id', $id)
            ->where('transaction_payments.method', '!=', 'advance')
            ->sum('transaction_payments.amount');

        $openingBalance = $contact->opening_balance ?? 0;
        
        $totalDue = ($totalSales + $openingBalance) - $totalPayments - $totalReturns;

        return response()->json([
            'total_sales' => $totalSales,
            'total_returns' => $totalReturns,
            'total_payments' => $totalPayments,
            'opening_balance' => $openingBalance,
            'total_due' => $totalDue
        ]);
    }

    /**
     * Receive a generic payment and distribute it over unpaid invoices.
     */
    public function receivePayment(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $contact = DB::table('contacts')->where('id', $id)->where('business_id', $businessId)->first();
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:cash,card,bank_transfer,bkash,sslcommerz',
            'note' => 'nullable|string',
            'paid_on' => 'nullable|date'
        ]);

        $amount = (float) $validated['amount'];
        $paidOn = $validated['paid_on'] ? Carbon::parse($validated['paid_on']) : Carbon::now();
        $remainingPayment = $amount;

        try {
            DB::beginTransaction();

            // Find unpaid invoices (oldest first)
            $unpaidInvoices = DB::table('transactions')
                ->where('contact_id', $id)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->where('amount_due', '>', 0)
                ->orderBy('transaction_date', 'asc')
                ->get();

            foreach ($unpaidInvoices as $invoice) {
                if ($remainingPayment <= 0) break;

                $payAmount = min($remainingPayment, $invoice->amount_due);

                // Insert payment record
                DB::table('transaction_payments')->insert([
                    'transaction_id' => $invoice->id,
                    'amount' => $payAmount,
                    'method' => $validated['method'],
                    'note' => $validated['note'] ?? 'Bulk Payment',
                    'paid_on' => $paidOn,
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // Update invoice
                $newDue = max(0, $invoice->amount_due - $payAmount);
                $paymentStatus = $newDue == 0 ? 'paid' : 'partial';

                DB::table('transactions')
                    ->where('id', $invoice->id)
                    ->update([
                        'amount_due' => $newDue,
                        'payment_status' => $paymentStatus,
                        'updated_at' => Carbon::now()
                    ]);

                $remainingPayment -= $payAmount;
            }

            // If there's still remaining payment, it's an advance.
            // Create a dummy transaction to hold the advance payment.
            if ($remainingPayment > 0.001) {
                $location = DB::table('locations')->where('business_id', $businessId)->first();
                $locationId = $location ? $location->id : 0; // Fallback, though a business should have a location.

                $advanceTxId = DB::table('transactions')->insertGetId([
                    'business_id' => $businessId,
                    'location_id' => $locationId,
                    'contact_id' => $id,
                    'created_by' => $request->user()->id,
                    'type' => 'advance_payment',
                    'status' => 'final',
                    'transaction_date' => $paidOn,
                    'total_before_tax' => $remainingPayment,
                    'final_total' => $remainingPayment,
                    'amount_due' => 0,
                    'payment_status' => 'paid',
                    'note' => 'Advance Payment',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

                DB::table('transaction_payments')->insert([
                    'transaction_id' => $advanceTxId,
                    'amount' => $remainingPayment,
                    'method' => $validated['method'],
                    'note' => $validated['note'] ?? 'Advance Bulk Payment',
                    'paid_on' => $paidOn,
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment received successfully',
                'amount_received' => $amount,
                'advance_amount' => $remainingPayment > 0 ? $remainingPayment : 0
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process payment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all customers with outstanding dues.
     */
    public function dueCustomers(Request $request)
    {
        $businessId = $request->user()->business_id;

        $dues = DB::select("
            SELECT c.id, c.name, c.mobile, c.email,
                   (IFNULL(c.opening_balance, 0) + IFNULL(sales.total, 0) - IFNULL(returns.total, 0) - IFNULL(payments.total, 0)) as total_due
            FROM contacts c
            LEFT JOIN (SELECT contact_id, SUM(final_total) as total FROM transactions WHERE business_id = ? AND type = 'sell' AND status = 'final' GROUP BY contact_id) sales ON c.id = sales.contact_id
            LEFT JOIN (SELECT contact_id, SUM(final_total) as total FROM transactions WHERE business_id = ? AND type = 'sell_return' AND status = 'final' GROUP BY contact_id) returns ON c.id = returns.contact_id
            LEFT JOIN (SELECT t.contact_id, SUM(tp.amount) as total FROM transaction_payments tp JOIN transactions t ON tp.transaction_id = t.id WHERE t.business_id = ? AND tp.method != 'advance' GROUP BY t.contact_id) payments ON c.id = payments.contact_id
            WHERE c.business_id = ? AND c.type IN ('customer', 'both')
            HAVING total_due > 0
            ORDER BY total_due DESC
        ", [$businessId, $businessId, $businessId, $businessId]);

        return response()->json($dues);
    }

    /**
     * Send an SMS payment reminder.
     */
    public function sendReminder(Request $request, $id)
    {
        $businessId = $request->user()->business_id;
        $contact = DB::table('contacts')->where('id', $id)->where('business_id', $businessId)->first();
        
        if (!$contact || empty($contact->mobile)) {
            return response()->json(['message' => 'Valid mobile number not found for contact.'], 400);
        }
        
        $amount = $request->input('amount');
        
        $business = DB::table('businesses')->where('id', $businessId)->first();
        $storeName = $business->name ?? 'Our Store';
        
        $body = "Dear {$contact->name}, your outstanding balance is {$amount}. Please clear your due at your earliest convenience. Thank you! - {$storeName}";
        
        $smsService = app(\App\Services\SmsGatewayService::class);
        $smsService->sendSms($contact->mobile, $body, $businessId);
        
        return response()->json(['message' => 'Reminder SMS queued successfully.']);
    }
}
