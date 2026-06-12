<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Requests\StoreBrandRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrandController extends Controller
{
    /**
     * Fetch all brands.
     */
    public function index(Request $request)
    {
        try {
            $query = Brand::query();

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $brands = $query->latest()->get();

            return response()->json([
                'status' => 'success',
                'data' => $brands
            ]);
        } catch (\Throwable $e) {
            Log::error('BrandController@index failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch brands.'
            ], 500);
        }
    }

    /**
     * Store a new brand.
     */
    public function store(StoreBrandRequest $request)
    {
        try {
            $brand = Brand::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Brand created successfully.',
                'data' => $brand
            ], 201);
        } catch (\Throwable $e) {
            Log::error('BrandController@store failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create brand.'
            ], 500);
        }
    }

    /**
     * Update an existing brand.
     */
    public function update(StoreBrandRequest $request, Brand $brand)
    {
        try {
            $brand->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Brand updated successfully.',
                'data' => $brand
            ]);
        } catch (\Throwable $e) {
            Log::error('BrandController@update failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update brand.'
            ], 500);
        }
    }

    /**
     * Delete a brand.
     */
    public function destroy(Brand $brand)
    {
        try {
            $brand->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand deleted successfully.'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete this item because it is linked to existing products.'
                ], 400);
            }
            Log::error('BrandController@destroy SQL failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete brand due to database constraint.'
            ], 500);
        } catch (\Throwable $e) {
            Log::error('BrandController@destroy failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete brand.'
            ], 500);
        }
    }
}
