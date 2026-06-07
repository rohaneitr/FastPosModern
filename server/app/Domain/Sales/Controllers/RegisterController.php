<?php

namespace App\Domain\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegisterController extends Controller
{
    /**
     * Get current register status for the user
     */
    public function status(Request $request)
    {
        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;

        $register = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

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

            return response()->json([
                'is_open' => true,
                'register' => $register,
                'cash_sales' => $cashPayments,
                'cash_expenses' => $cashExpenses,
                'expected_cash' => $register->opening_amount + $cashPayments - $cashExpenses
            ]);
        }

        return response()->json(['is_open' => false]);
    }

    /**
     * Open a new register session
     */
    public function open(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0'
        ]);

        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;

        // Check if already open
        $exists = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Register is already open.'], 400);
        }

        $id = DB::table('cash_registers')->insertGetId([
            'business_id' => $businessId,
            'user_id' => $userId,
            'status' => 'open',
            'opening_amount' => $request->opening_amount,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Register opened successfully', 'register_id' => $id], 201);
    }

    /**
     * Close the current register session
     */
    public function close(Request $request)
    {
        $request->validate([
            'counted_cash' => 'required|numeric|min:0'
        ]);

        $userId = $request->user()->id;
        $businessId = $request->user()->business_id;

        $register = DB::table('cash_registers')
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$register) {
            return response()->json(['message' => 'No open register found.'], 400);
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

        $expectedCash = $register->opening_amount + $cashPayments - $cashExpenses;
        $discrepancy = $request->counted_cash - $expectedCash;

        DB::table('cash_registers')
            ->where('id', $register->id)
            ->update([
                'status' => 'closed',
                'closing_amount' => $request->counted_cash,
                'total_cash_sales' => $cashPayments,
                'closed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        return response()->json([
            'message' => 'Register closed successfully',
            'expected_cash' => $expectedCash,
            'counted_cash' => $request->counted_cash,
            'discrepancy' => $discrepancy
        ]);
    }
}
