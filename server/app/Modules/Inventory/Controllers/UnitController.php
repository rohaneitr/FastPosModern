<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Requests\StoreUnitRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UnitController extends Controller
{
    /**
     * Fetch all units.
     */
    public function index(Request $request)
    {
        try {
            $query = Unit::query();

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('short_name', 'like', '%' . $request->search . '%');
            }

            $units = $query->latest()->get();

            return response()->json([
                'status' => 'success',
                'data' => $units
            ]);
        } catch (\Throwable $e) {
            Log::error('UnitController@index failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch units.'
            ], 500);
        }
    }

    /**
     * Store a new unit.
     */
    public function store(StoreUnitRequest $request)
    {
        try {
            $unit = Unit::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Unit created successfully.',
                'data' => $unit
            ], 201);
        } catch (\Throwable $e) {
            Log::error('UnitController@store failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create unit.'
            ], 500);
        }
    }

    /**
     * Update an existing unit.
     */
    public function update(StoreUnitRequest $request, Unit $unit)
    {
        try {
            $unit->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Unit updated successfully.',
                'data' => $unit
            ]);
        } catch (\Throwable $e) {
            Log::error('UnitController@update failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update unit.'
            ], 500);
        }
    }

    /**
     * Delete a unit.
     */
    public function destroy(Unit $unit)
    {
        try {
            $unit->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Unit deleted successfully.'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete this item because it is linked to existing products.'
                ], 400);
            }
            Log::error('UnitController@destroy SQL failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete unit due to database constraint.'
            ], 500);
        } catch (\Throwable $e) {
            Log::error('UnitController@destroy failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete unit.'
            ], 500);
        }
    }
}
