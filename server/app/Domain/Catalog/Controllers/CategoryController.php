<?php

namespace App\Domain\Catalog\Controllers;

use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories for the authenticated user's business.
     */
    public function index(Request $request)
    {
        $categories = Category::orderBy('name')
            ->paginate($request->get('per_page', 50));

        return response()->json($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('business_id', $businessId)
            ],
            'short_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', $businessId)
            ],
        ]);

        $validated['created_by'] = $request->user()->id;

        $category = Category::create($validated);

        return response()->json(['message' => 'Category created successfully', 'data' => $category], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        return response()->json($category);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('business_id', $businessId)
                    ->ignore($category->id)
            ],
            'short_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', $businessId)
            ],
        ]);

        $category->update($validated);

        return response()->json(['message' => 'Category updated successfully', 'data' => $category]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category)
    {
        $productsCount = \App\Domain\Catalog\Models\Product::where('category_id', $category->id)->count();
        if ($productsCount > 0) {
            return response()->json(['message' => 'Cannot delete category with associated products.'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
