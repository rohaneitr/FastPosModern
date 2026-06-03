<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class LocationController extends Controller
{
    /**
     * List all locations for the authenticated business.
     */
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;

        $locations = DB::table('locations')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'landmark', 'city', 'state', 'country', 'zip_code', 'mobile', 'created_at')
            ->orderBy('name')
            ->get();

        // Attach product count per location
        foreach ($locations as &$loc) {
            $loc->product_count = DB::table('product_stocks')
                ->where('location_id', $loc->id)
                ->where('qty_available', '>', 0)
                ->count();
        }

        return response()->json($locations);
    }

    /**
     * Create a new location.
     */
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->where('business_id', $businessId)],
            'landmark' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:30',
        ]);

        $id = DB::table('locations')->insertGetId(array_merge($validated, [
            'business_id' => $businessId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        return response()->json(['message' => 'Location created', 'id' => $id], 201);
    }

    /**
     * Update a location.
     */
    public function update(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $location = DB::table('locations')
            ->where('id', $id)->where('business_id', $businessId)->whereNull('deleted_at')
            ->first();

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->where('business_id', $businessId)->ignore($id)],
            'landmark' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:30',
        ]);

        DB::table('locations')->where('id', $id)->update(array_merge($validated, [
            'updated_at' => Carbon::now(),
        ]));

        return response()->json(['message' => 'Location updated']);
    }

    /**
     * Soft-delete a location.
     */
    public function destroy(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $deleted = DB::table('locations')
            ->where('id', $id)->where('business_id', $businessId)->whereNull('deleted_at')
            ->update(['deleted_at' => Carbon::now()]);

        if (!$deleted) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        return response()->json(['message' => 'Location deleted']);
    }
}
