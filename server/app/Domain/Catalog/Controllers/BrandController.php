<?php

namespace App\Domain\Catalog\Controllers;

use App\Domain\Catalog\Models\Brand;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::orderBy('name')->paginate($request->get('per_page', 50));
        return response()->json($brands);
    }

    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')->where('business_id', $businessId)
            ],
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = $request->user()->id;

        $brand = Brand::create($validated);

        return response()->json(['message' => 'Brand created successfully', 'data' => $brand], 201);
    }

    public function show(Brand $brand)
    {
        return response()->json($brand);
    }

    public function update(Request $request, Brand $brand)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')
                    ->where('business_id', $businessId)
                    ->ignore($brand->id)
            ],
            'description' => 'nullable|string',
        ]);

        $brand->update($validated);

        return response()->json(['message' => 'Brand updated successfully', 'data' => $brand]);
    }

    public function destroy(Brand $brand)
    {
        $productsCount = \App\Domain\Catalog\Models\Product::where('brand_id', $brand->id)->count();
        if ($productsCount > 0) {
            return response()->json(['message' => 'Cannot delete brand with associated products.'], 422);
        }

        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }
}
