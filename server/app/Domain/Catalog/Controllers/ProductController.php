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

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            // Escape search string for safety in raw query
            $safeSearch = addslashes($search);
            
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('generic_name', 'like', "%{$search}%");
            })
            // Sort by relevance: items starting with the exact search string get highest priority
            ->orderByRaw("CASE 
                WHEN name LIKE '{$safeSearch}%' THEN 1 
                WHEN generic_name LIKE '{$safeSearch}%' THEN 2 
                WHEN sku = '{$safeSearch}' THEN 0
                ELSE 3 
            END ASC")
            ->orderBy('name', 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        $products = $query->paginate($request->get('per_page', 50));

        // RBAC API Masking
        $userRole = $request->user()->roles->first()->name ?? '';
        $isAdmin = in_array($userRole, ['BusinessAdmin', 'Admin', 'Manager']);
        
        $items = collect($products->items())->map(function ($product) use ($isAdmin) {
            if (!$isAdmin) {
                // Mask purchase price attributes
                unset($product->purchase_price);
                if ($product->variations) {
                    foreach ($product->variations as $variation) {
                        unset($variation->default_purchase_price);
                        unset($variation->purchase_price);
                    }
                }
            }
            return $product;
        });

        return response()->json([
            'data' => $items,
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
            'has_serial_number' => 'nullable|boolean',
            'warranty_days' => 'nullable|integer|min:0',
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
                'has_serial_number' => $validated['has_serial_number'] ?? false,
                'warranty_days' => $validated['warranty_days'] ?? 0,
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
     * Update the specified product.
     */
    public function update(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                Rule::unique('products', 'sku')->where('business_id', $businessId)->ignore($id)
            ],
            'has_serial_number' => 'nullable|boolean',
            'warranty_days' => 'nullable|integer|min:0',
        ]);

        $product = Product::where('business_id', $businessId)->findOrFail($id);
        
        $product->update([
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'has_serial_number' => $validated['has_serial_number'] ?? false,
            'warranty_days' => $validated['warranty_days'] ?? 0,
        ]);

        return response()->json(['message' => 'Product updated successfully', 'product' => $product]);
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
        $printPayload = [];
        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['id']);
            for ($i = 0; $i < $item['labels_count']; $i++) {
                $printPayload[] = [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode_type' => $product->barcode_type,
                    'price' => DB::table('variations')->where('product_id', $product->id)->value('sell_price_inc_tax') ?? 0,
                    'business_name' => DB::table('businesses')->where('id', $product->business_id)->value('name'),
                ];
            }
        }

        return response()->json([
            'message' => 'Label payload generated',
            'labels' => $printPayload,
            'settings' => $validated['print_settings']
        ]);
    }

    /**
     * Get available serial numbers for a product
     */
    public function getAvailableSerials(Request $request, $id)
    {
        $businessId = $request->user()->business_id;
        $serials = DB::table('product_serials')
            ->where('business_id', $businessId)
            ->where('product_id', $id)
            ->where('status', 'available')
            ->pluck('serial_number');
            
        return response()->json($serials);
    }
    
    /**
     * Check warranty status of a serial number
     */
    public function checkWarranty(Request $request)
    {
        $businessId = $request->user()->business_id;
        $serial = $request->query('serial');
        
        if (!$serial) {
            return response()->json(['message' => 'Serial number required'], 400);
        }
        
        $record = DB::table('product_serials')
            ->join('products', 'product_serials.product_id', '=', 'products.id')
            ->leftJoin('transactions', 'product_serials.transaction_id', '=', 'transactions.id')
            ->where('product_serials.business_id', $businessId)
            ->where('product_serials.serial_number', $serial)
            ->select(
                'products.name as product_name',
                'products.warranty_days',
                'transactions.invoice_no',
                'transactions.transaction_date',
                'transactions.contact_id',
                'product_serials.status',
                'product_serials.warranty_start_date',
                'product_serials.transaction_id',
                'product_serials.product_id'
            )
            ->first();
            
        if (!$record) {
            return response()->json(['message' => 'Serial number not found in system.'], 404);
        }
        
        if ($record->status !== 'sold' || !$record->warranty_start_date) {
            return response()->json([
                'status' => 'INVALID',
                'message' => 'Product has not been sold or warranty not activated.',
                'details' => $record
            ]);
        }
        
        $start = \Carbon\Carbon::parse($record->warranty_start_date);
        $expiry = $start->copy()->addDays($record->warranty_days);
        $now = \Carbon\Carbon::now();
        
        $isValid = $now->lte($expiry);
        
        $customerName = 'Walk-in Customer';
        if ($record->contact_id) {
            $contact = DB::table('contacts')->where('id', $record->contact_id)->first();
            if ($contact) {
                $customerName = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: ($contact->name ?? 'Unknown');
            }
        }
        
        return response()->json([
            'status' => $isValid ? 'VALID' : 'EXPIRED',
            'product_name' => $record->product_name,
            'customer_name' => $customerName,
            'invoice_no' => $record->invoice_no,
            'sale_date' => $record->transaction_date,
            'warranty_start' => $record->warranty_start_date,
            'warranty_end' => $expiry->toDateTimeString(),
            'days_remaining' => $isValid ? $now->diffInDays($expiry) : 0,
            'transaction_id' => $record->transaction_id,
            'product_id' => $record->product_id,
            'contact_id' => $record->contact_id,
            'serial_number' => $serial,
        ]);
    }

    /**
     * Get generic alternatives for a specific medicine
     */
    public function genericAlternatives(Request $request)
    {
        $businessId = $request->user()->business_id;
        $genericName = $request->query('generic_name');

        if (!$genericName) {
            return response()->json([]);
        }

        $alternatives = Product::with(['variations'])
            ->where('business_id', $businessId)
            ->where('generic_name', $genericName)
            ->where('is_medicine', true)
            ->orderBy('name')
            ->take(20)
            ->get();

        return response()->json($alternatives);
    }
}
