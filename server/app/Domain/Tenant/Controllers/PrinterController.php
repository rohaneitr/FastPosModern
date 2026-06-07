<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrinterController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(DB::table('printers')
            ->where('business_id', $request->user()->business_id)
            ->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'connection_type' => 'required|string|in:network,windows,linux,browser',
            'capability_profile' => 'nullable|string|in:default,simple,star,epson',
            'char_per_line' => 'nullable|integer',
            'ip_address' => 'nullable|string|max:255',
            'port' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
        ]);

        $id = DB::table('printers')->insertGetId(array_merge($validated, [
            'business_id' => $request->user()->business_id,
            'created_by' => $request->user()->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        return response()->json(['message' => 'Printer created', 'id' => $id], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'connection_type' => 'required|string|in:network,windows,linux,browser',
            'capability_profile' => 'nullable|string|in:default,simple,star,epson',
            'char_per_line' => 'nullable|integer',
            'ip_address' => 'nullable|string|max:255',
            'port' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
        ]);

        DB::table('printers')
            ->where('id', $id)->where('business_id', $request->user()->business_id)
            ->update(array_merge($validated, ['updated_at' => Carbon::now()]));

        return response()->json(['message' => 'Printer updated']);
    }

    public function destroy(Request $request, $id)
    {
        DB::table('printers')
            ->where('id', $id)->where('business_id', $request->user()->business_id)
            ->delete();

        return response()->json(['message' => 'Printer deleted']);
    }
}
