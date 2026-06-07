<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaxRateController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(DB::table('tax_rates')
            ->where('business_id', $request->user()->business_id)
            ->whereNull('deleted_at')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
        ]);

        $id = DB::table('tax_rates')->insertGetId(array_merge($validated, [
            'business_id' => $request->user()->business_id,
            'created_by' => $request->user()->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        return response()->json(['message' => 'Tax rate created', 'id' => $id], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
        ]);

        DB::table('tax_rates')
            ->where('id', $id)->where('business_id', $request->user()->business_id)
            ->update(array_merge($validated, ['updated_at' => Carbon::now()]));

        return response()->json(['message' => 'Tax rate updated']);
    }

    public function destroy(Request $request, $id)
    {
        DB::table('tax_rates')
            ->where('id', $id)->where('business_id', $request->user()->business_id)
            ->update(['deleted_at' => Carbon::now()]);

        return response()->json(['message' => 'Tax rate deleted']);
    }
}
