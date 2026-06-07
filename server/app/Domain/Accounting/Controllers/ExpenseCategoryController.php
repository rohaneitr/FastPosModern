<?php

namespace App\Domain\Accounting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = DB::table('expense_categories')
            ->where('business_id', $request->user()->business_id)
            ->whereNull('deleted_at')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50'
        ]);

        $id = DB::table('expense_categories')->insertGetId([
            'business_id' => $request->user()->business_id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Category created', 'id' => $id], 201);
    }

    public function destroy(Request $request, $id)
    {
        DB::table('expense_categories')
            ->where('business_id', $request->user()->business_id)
            ->where('id', $id)
            ->update(['deleted_at' => Carbon::now()]);

        return response()->json(['message' => 'Category deleted']);
    }
}
