<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Requests\StoreCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Fetch all categories.
     */
    public function index(Request $request)
    {
        try {
            $query = Category::query();

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $categories = $query->latest()->get();

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Throwable $e) {
            Log::error('CategoryController@index failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories.'
            ], 500);
        }
    }

    /**
     * Store a new category.
     */
    public function store(StoreCategoryRequest $request)
    {
        try {
            $category = Category::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully.',
                'data' => $category
            ], 201);
        } catch (\Throwable $e) {
            Log::error('CategoryController@store failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category.'
            ], 500);
        }
    }

    /**
     * Update an existing category.
     */
    public function update(StoreCategoryRequest $request, Category $category)
    {
        try {
            $category->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully.',
                'data' => $category
            ]);
        } catch (\Throwable $e) {
            Log::error('CategoryController@update failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category.'
            ], 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category)
    {
        try {
            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully.'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete this item because it is linked to existing products.'
                ], 400);
            }
            Log::error('CategoryController@destroy SQL failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete category due to database constraint.'
            ], 500);
        } catch (\Throwable $e) {
            Log::error('CategoryController@destroy failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete category.'
            ], 500);
        }
    }
}
