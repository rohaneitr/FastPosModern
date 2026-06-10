<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Requests\StoreProductRequest;
use App\Modules\Inventory\Requests\UpdateProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::with(['category', 'brand', 'unit', 'tax']);

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $products = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            if (empty($validated['sku'])) {
                $validated['sku'] = 'PRD-' . strtoupper(Str::random(8));
            }

            $product = Product::create($validated);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product->load(['category', 'brand', 'unit'])
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating product: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Product $product)
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $product->load(['category', 'brand', 'unit'])
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching product: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            DB::beginTransaction();

            $product->update($request->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product->fresh(['category', 'brand', 'unit'])
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            DB::beginTransaction();
            
            $product->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function serials(Product $product)
    {
        try {
            $serials = DB::table('inventory_item_serials')
                ->where('product_id', $product->id)
                ->where('status', 'Available')
                ->get();
            
            $available = $serials->map(function ($item) {
                if (!empty($item->serial_number)) {
                    return $item->serial_number;
                }
                if (!empty($item->imei_number)) {
                    return $item->imei_number;
                }
                return null;
            })->filter()->values()->toArray();

            return response()->json($available);
        } catch (\Throwable $e) {
            Log::error('Error fetching serials: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }
}
