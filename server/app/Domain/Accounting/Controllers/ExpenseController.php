<?php

namespace App\Domain\Accounting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $expenses = DB::table('expenses')
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->leftJoin('locations', 'expenses.location_id', '=', 'locations.id')
            ->where('expenses.business_id', $request->user()->business_id)
            ->whereNull('expenses.deleted_at')
            ->select(
                'expenses.*',
                'expense_categories.name as category_name',
                'locations.name as location_name'
            )
            ->orderBy('expense_date', 'desc')
            ->paginate(20);

        return response()->json($expenses);
    }

    /**
     * Store a newly created expense in storage.
     */
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('business_id', $businessId)
            ],
            'expense_category_id' => [
                'nullable',
                Rule::exists('expense_categories', 'id')->where('business_id', $businessId)
            ],
            'total_amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $expenseId = DB::table('expenses')->insertGetId([
            'business_id' => $request->user()->business_id,
            'location_id' => $validated['location_id'] ?? null,
            'expense_category_id' => $validated['expense_category_id'] ?? null,
            'created_by' => $request->user()->id,
            'reference_no' => 'EXP-' . time(),
            'total_amount' => $validated['total_amount'],
            'expense_date' => Carbon::parse($validated['expense_date'])->format('Y-m-d H:i:s'),
            'note' => $validated['note'] ?? null,
            'payment_status' => 'paid', // simple default for now
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Expense logged successfully',
            'id' => $expenseId
        ], 201);
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $expense = DB::table('expenses')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->first();

        if (!$expense) {
            return response()->json(['message' => 'Expense not found or unauthorized'], 404);
        }

        $validated = $request->validate([
            'location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('business_id', $businessId)
            ],
            'expense_category_id' => [
                'nullable',
                Rule::exists('expense_categories', 'id')->where('business_id', $businessId)
            ],
            'total_amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        DB::table('expenses')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->update([
                'location_id' => $validated['location_id'] ?? null,
                'expense_category_id' => $validated['expense_category_id'] ?? null,
                'total_amount' => $validated['total_amount'],
                'expense_date' => Carbon::parse($validated['expense_date'])->format('Y-m-d H:i:s'),
                'note' => $validated['note'] ?? null,
                'updated_at' => Carbon::now(),
            ]);

        return response()->json(['message' => 'Expense updated successfully']);
    }

    /**
     * Soft-delete the specified expense.
     */
    public function destroy(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $deleted = DB::table('expenses')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => Carbon::now()]);

        if (!$deleted) {
            return response()->json(['message' => 'Expense not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Expense deleted successfully']);
    }
}
