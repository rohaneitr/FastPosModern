<?php

namespace App\Domain\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the products for the authenticated user's business.
     */
    public function index(Request $request)
    {
        // Notice how TenantModel automatically scopes this to the current user's business_id!
        $query = Product::with(['unit', 'brand', 'category', 'variations']);

        if ($request->has('updated_since')) {
            $query->withTrashed()
                  ->where('updated_at', '>=', $request->updated_since);
        }

        $products = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
            'sync_timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:single,variable,combo',
            'unit_id' => [
                'required',
                Rule::exists('units', 'id')->where('business_id', $businessId)
            ],
            'sku' => [
                'required',
                'string',
                Rule::unique('products', 'sku')->where('business_id', $businessId)
            ],
            'barcode_type' => 'nullable|string',
            'attributes' => 'nullable|array', // For combo items
            'variations' => 'nullable|array', // For variable products
        ]);

        try {
            DB::beginTransaction();
            
            $productData = [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'unit_id' => $validated['unit_id'],
                'sku' => $validated['sku'],
                'barcode_type' => $validated['barcode_type'] ?? 'C128',
                'business_id' => $request->user()->business_id,
                'created_by' => $request->user()->id,
            ];
            
            if ($validated['type'] === 'combo' && !empty($validated['attributes'])) {
                $productData['attributes'] = json_encode(['combo_details' => $validated['attributes']]);
            }

            $product = Product::create($productData);

            if ($validated['type'] === 'variable' && !empty($validated['variations'])) {
                foreach ($validated['variations'] as $v) {
                    DB::table('variations')->insert([
                        'product_id' => $product->id,
                        'name' => $v['name'],
                        'sub_sku' => $v['sub_sku'] ?? ($product->sku . '-' . rand(10,99)),
                        'default_purchase_price' => $v['default_purchase_price'] ?? 0,
                        'sell_price_inc_tax' => $v['sell_price_inc_tax'] ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
            return response()->json($product->load('variations'), 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create advanced product', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show($id)
    {
        // Scoped automatically, will return 404 if the product belongs to another business.
        $product = Product::with(['unit', 'brand', 'category', 'variations'])->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Generate Label print payload based on selected products
     */
    public function printLabels(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', $businessId)
            ],
            'products.*.labels_count' => 'required|integer|min:1',
            'print_settings' => 'required|array',
        ]);

        // Fetch product barcodes for rendering
        $productIds = collect($validated['products'])->pluck('id')->toArray();
        $products = Product::with(['variations', 'business'])->whereIn('id', $productIds)->get()->keyBy('id');

        $printPayload = [];
        foreach ($validated['products'] as $item) {
            $product = $products->get($item['id']);
            if (!$product) continue;
            
            $price = $product->variations->first()->sell_price_inc_tax ?? 0;
            $businessName = $product->business->name ?? '';

            for ($i = 0; $i < $item['labels_count']; $i++) {
                $printPayload[] = [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode_type' => $product->barcode_type,
                    'price' => $price,
                    'business_name' => $businessName,
                ];
            }
        }

        return response()->json([
            'message' => 'Label payload generated',
            'labels' => $printPayload,
            'settings' => $validated['print_settings']
        ]);
    }
}
