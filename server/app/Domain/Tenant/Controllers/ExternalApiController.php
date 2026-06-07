<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalApiController extends Controller
{
    /**
     * Get products for external e-commerce consumption.
     */
    public function getProducts(Request $request)
    {
        $user = $request->user();
        
        // Strictly scope to the token's associated business_id
        $businessId = $user->business_id;

        $query = DB::table('products')
            ->join('product_variations', 'products.id', '=', 'product_variations.product_id')
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.business_id', $businessId)
            ->where('products.is_active', true)
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.image',
                'products.description',
                'product_variations.sell_price_inc_tax as price',
                DB::raw('SUM(product_stocks.qty_available) as stock_quantity')
            )
            ->groupBy(
                'products.id', 
                'products.name', 
                'products.sku', 
                'products.image',
                'products.description',
                'product_variations.sell_price_inc_tax'
            );

        $products = $query->paginate(20);

        return response()->json($products);
    }
}
