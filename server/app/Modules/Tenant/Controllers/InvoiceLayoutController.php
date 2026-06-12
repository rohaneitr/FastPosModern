<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class InvoiceLayoutController extends Controller
{
    /**
     * List all invoice layouts for the business.
     */
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $layouts = DB::table('invoice_layouts')
            ->where('business_id', $businessId)
            ->get();
            
        return response()->json($layouts);
    }

    /**
     * Create a new invoice layout.
     */
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'show_logo' => 'boolean',
            'show_business_name' => 'boolean',
            'show_location_name' => 'boolean',
            'show_customer' => 'boolean',
            'show_barcode' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            DB::table('invoice_layouts')->where('business_id', $businessId)->update(['is_default' => false]);
        }

        $id = DB::table('invoice_layouts')->insertGetId(array_merge($validated, [
            'business_id' => $businessId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        return response()->json(['message' => 'Invoice layout created', 'id' => $id], 201);
    }

    /**
     * Update an invoice layout.
     */
    public function update(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $layout = DB::table('invoice_layouts')
            ->where('id', $id)->where('business_id', $businessId)
            ->first();

        if (!$layout) {
            return response()->json(['message' => 'Layout not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'show_logo' => 'boolean',
            'show_business_name' => 'boolean',
            'show_location_name' => 'boolean',
            'show_customer' => 'boolean',
            'show_barcode' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            DB::table('invoice_layouts')->where('business_id', $businessId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        DB::table('invoice_layouts')->where('id', $id)->update(array_merge($validated, [
            'updated_at' => Carbon::now(),
        ]));

        return response()->json(['message' => 'Invoice layout updated']);
    }

    /**
     * Delete an invoice layout.
     */
    public function destroy(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $deleted = DB::table('invoice_layouts')
            ->where('id', $id)->where('business_id', $businessId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Layout not found'], 404);
        }

        return response()->json(['message' => 'Invoice layout deleted']);
    }
}
