<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegisterController extends Controller
{
    /**
     * Get current register status for the user and device
     */
    public function status(Request $request)
    {
        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;
        $deviceHash = $request->header('X-Device-Hash') ?? $request->input('device_hash');

        if (!$deviceHash) {
            return response()->json(['message' => 'Device hash is required.'], 400);
        }

        $business = DB::table('businesses')->where('id', $businessId)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];
        $flags = [
            'pos_enforce_device_lock' => $settings['pos_enforce_device_lock'] ?? true,
            'pos_enforce_strict_cash_control' => $settings['pos_enforce_strict_cash_control'] ?? true,
        ];

        // If strict cash control is globally disabled for this tenant, always return is_open: true gracefully
        if (!$flags['pos_enforce_strict_cash_control']) {
            return response()->json([
                'is_open' => true,
                'status' => 'open',
                'settings' => $flags,
                'register' => null,
                'cash_sales' => 0,
                'cash_expenses' => 0,
                'expected_cash' => 0
            ]);
        }

        $query = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('opened_by_user_id', $userId)
            ->whereIn('status', ['open', 'suspending']);
            
        if ($flags['pos_enforce_device_lock']) {
            $query->where('device_hash', $deviceHash);
        }
        
        $register = $query->first();

        if ($register) {
            // Calculate all cash inflows (sales + due payments)
            $cashPayments = DB::table('transaction_payments')
                ->where('created_by', $userId)
                ->where('method', 'cash')
                ->where('created_at', '>=', $register->created_at)
                ->sum('amount');

            // Calculate cash outflows (expenses)
            $cashExpenses = DB::table('expenses')
                ->where('created_by', $userId)
                ->where('business_id', $businessId)
                ->where('payment_method', 'cash')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $register->created_at)
                ->sum('total_amount');

            $expectedCash = $register->status === 'suspending' 
                ? $register->closing_balance_expected 
                : $register->opening_balance + $cashPayments - $cashExpenses;

            return response()->json([
                'is_open' => true,
                'status' => $register->status,
                'settings' => $flags,
                'register' => $register,
                'cash_sales' => $cashPayments,
                'cash_expenses' => $cashExpenses,
                'expected_cash' => $expectedCash
            ]);
        }

        return response()->json(['is_open' => false, 'settings' => $flags]);
    }

    /**
     * Open a new register session
     */
    public function open(Request $request)
    {
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'device_hash' => 'nullable|string'
        ]);

        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;
        
        $business = DB::table('businesses')->where('id', $businessId)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];
        $enforceDeviceLock = $settings['pos_enforce_device_lock'] ?? true;

        $deviceHash = $request->header('X-Device-Hash') ?? $request->input('device_hash');

        if ($enforceDeviceLock && !$deviceHash) {
            return response()->json(['message' => 'Device hash is required to securely lock this register.'], 400);
        }

        // Check if already open on any device
        $exists = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('opened_by_user_id', $userId)
            ->whereIn('status', ['open', 'suspending'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You already have an active register open. Close it before opening a new one.'], 400);
        }

        $id = DB::table('cash_registers')->insertGetId([
            'business_id' => $businessId,
            'device_hash' => $deviceHash ?? 'bypassed',
            'opened_by_user_id' => $userId,
            'status' => 'open',
            'opening_balance' => $request->opening_balance,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Register opened successfully', 'register_id' => $id], 201);
    }

    /**
     * Suspend the register session to physically count cash
     */
    public function suspend(Request $request)
    {
        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;
        
        $business = DB::table('businesses')->where('id', $businessId)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];
        $enforceDeviceLock = $settings['pos_enforce_device_lock'] ?? true;

        $deviceHash = $request->header('X-Device-Hash') ?? $request->input('device_hash');

        $query = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('opened_by_user_id', $userId)
            ->where('status', 'open');

        if ($enforceDeviceLock) {
            $query->where('device_hash', $deviceHash);
        }

        $register = $query->first();

        if (!$register) {
            return response()->json(['message' => 'No open register found for this device.'], 400);
        }

        $cashPayments = DB::table('transaction_payments')
            ->where('created_by', $userId)
            ->where('method', 'cash')
            ->where('created_at', '>=', $register->created_at)
            ->sum('amount');

        $cashExpenses = DB::table('expenses')
            ->where('created_by', $userId)
            ->where('business_id', $businessId)
            ->where('payment_method', 'cash')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $register->created_at)
            ->sum('total_amount');

        $expectedCash = $register->opening_balance + $cashPayments - $cashExpenses;

        DB::table('cash_registers')
            ->where('id', $register->id)
            ->update([
                'status' => 'suspending',
                'closing_balance_expected' => $expectedCash,
                'updated_at' => Carbon::now()
            ]);

        return response()->json([
            'message' => 'Register suspended for Z-Report closure',
            'expected_cash' => $expectedCash
        ]);
    }

    /**
     * Close the current register session
     */
    public function close(Request $request)
    {
        $request->validate([
            'closing_balance_counted' => 'required|numeric|min:0'
        ]);

        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;
        
        $business = DB::table('businesses')->where('id', $businessId)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];
        $enforceDeviceLock = $settings['pos_enforce_device_lock'] ?? true;

        $deviceHash = $request->header('X-Device-Hash') ?? $request->input('device_hash');

        $query = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('opened_by_user_id', $userId)
            ->whereIn('status', ['open', 'suspending']);

        if ($enforceDeviceLock) {
            $query->where('device_hash', $deviceHash);
        }

        $register = $query->first();

        if (!$register) {
            return response()->json(['message' => 'No active register found for this device.'], 400);
        }

        // If not suspending yet, calculate expected cash now (fail-safe)
        $expectedCash = $register->closing_balance_expected;
        
        if ($register->status === 'open') {
            $cashPayments = DB::table('transaction_payments')
                ->where('created_by', $userId)
                ->where('method', 'cash')
                ->where('created_at', '>=', $register->created_at)
                ->sum('amount');

            $cashExpenses = DB::table('expenses')
                ->where('created_by', $userId)
                ->where('business_id', $businessId)
                ->where('payment_method', 'cash')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $register->created_at)
                ->sum('total_amount');

            $expectedCash = $register->opening_balance + $cashPayments - $cashExpenses;
        }

        $countedCash = $request->closing_balance_counted;
        $discrepancy = $countedCash - $expectedCash;

        DB::beginTransaction();

        try {
            DB::table('cash_registers')
                ->where('id', $register->id)
                ->update([
                    'status' => 'closed',
                    'closing_balance_expected' => $expectedCash,
                    'closing_balance_counted' => $countedCash,
                    'discrepancy_amount' => $discrepancy,
                    'closed_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            if ($discrepancy < 0) {
                // Shortage - Debit Cash Discrepancy, Credit Cash
                $discrepancyAmount = \App\Modules\Sales\Services\FinancialCalculator::of(abs($discrepancy))->toScale(4)->__toString();
                
                $debits = [
                    [
                        'chart_of_account_id' => \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH_DISCREPANCY),
                        'amount' => $discrepancyAmount
                    ]
                ];
                $credits = [
                    [
                        'chart_of_account_id' => \App\Modules\Finance\Services\TenantAccountResolver::resolve($businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH),
                        'amount' => $discrepancyAmount
                    ]
                ];

                $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);
                $ledger->recordEntry(
                    $businessId,
                    'SHIFT-' . $register->id,
                    now()->toDateString(),
                    "Register Shift Shortage",
                    $debits,
                    $credits,
                    $register->id,
                    'register_closure',
                    $userId
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Register closed successfully',
                'closing_balance_expected' => $expectedCash,
                'closing_balance_counted' => $countedCash,
                'discrepancy_amount' => $discrepancy
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to close register.', 'error' => $e->getMessage()], 500);
        }
    }
}
