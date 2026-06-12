<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Modules\Inventory\Services\BOMDeductionService;
use App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction;

class RestaurantBOMTest extends TestCase
{
    use RefreshDatabase;

    public function test_recursive_bom_deduction_for_composite_product()
    {
        // 1. Setup Business & Categories
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Test Restaurant',
            'plan_type' => 'enterprise',
            'active_modules' => json_encode(['restaurant']),
        ]);

        $catId = DB::table('categories')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Food',
        ]);

        // 2. Setup Products
        // Raw Materials
        $bunId = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'category_id' => $catId,
            'name' => 'Burger Bun',
            'sku' => 'RAW-BUN-1',
            'type' => 'standard',
            'purchase_price' => 0.50,
            'sell_price_inc_tax' => 0.00,
        ]);

        $pattyId = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'category_id' => $catId,
            'name' => 'Beef Patty',
            'sku' => 'RAW-PATTY-1',
            'type' => 'standard',
            'purchase_price' => 1.50,
            'sell_price_inc_tax' => 0.00,
        ]);

        // Composite Product
        $burgerId = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'category_id' => $catId,
            'name' => 'Classic Burger',
            'sku' => 'COMBO-BURGER',
            'type' => 'composite',
            'purchase_price' => 2.00,
            'sell_price_inc_tax' => 8.99,
        ]);

        // 3. Setup Bill of Materials (BOM)
        DB::table('product_assemblies')->insert([
            ['parent_product_id' => $burgerId, 'child_product_id' => $bunId, 'quantity' => 1],
            ['parent_product_id' => $burgerId, 'child_product_id' => $pattyId, 'quantity' => 1],
        ]);

        // 4. Setup Inventory Layers (Stocking the kitchen)
        DB::table('inventory_layers')->insert([
            ['business_id' => $businessId, 'product_id' => $bunId, 'remaining_qty' => 10, 'unit_cost' => 0.50],
            ['business_id' => $businessId, 'product_id' => $pattyId, 'remaining_qty' => 5, 'unit_cost' => 1.50],
        ]);

        // 5. Execute Deduction
        $service = new BOMDeductionService(new ConsumeBatchFIFOInventoryAction());
        $service->deductForOrder($businessId, $burgerId, 1, 'Order #999');

        // 6. Assertions
        $bunStock = DB::table('inventory_layers')->where('product_id', $bunId)->sum('remaining_qty');
        $this->assertEquals(9, $bunStock, "Bun stock should be deducted by 1.");

        $pattyStock = DB::table('inventory_layers')->where('product_id', $pattyId)->sum('remaining_qty');
        $this->assertEquals(4, $pattyStock, "Patty stock should be deducted by 1.");

        // Assert Audit Logs
        $historyCount = DB::table('stock_history')->where('reference', 'Order #999')->count();
        $this->assertEquals(2, $historyCount, "Should have 2 stock history records (1 for each ingredient).");
    }

    public function test_bom_deduction_fails_if_insufficient_ingredients()
    {
        // 1. Setup Business & Categories
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Test Restaurant',
            'plan_type' => 'enterprise',
            'active_modules' => json_encode(['restaurant']),
        ]);

        $catId = DB::table('categories')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Food',
        ]);

        // 2. Setup Products
        $bunId = DB::table('products')->insertGetId([
            'business_id' => $businessId, 'category_id' => $catId, 'name' => 'Burger Bun', 'sku' => 'RAW-BUN', 'type' => 'standard', 'purchase_price' => 0.50, 'sell_price_inc_tax' => 0.00,
        ]);
        $burgerId = DB::table('products')->insertGetId([
            'business_id' => $businessId, 'category_id' => $catId, 'name' => 'Classic Burger', 'sku' => 'COMBO-BURGER', 'type' => 'composite', 'purchase_price' => 2.00, 'sell_price_inc_tax' => 8.99,
        ]);

        // 3. Setup Bill of Materials (BOM)
        DB::table('product_assemblies')->insert([
            ['parent_product_id' => $burgerId, 'child_product_id' => $bunId, 'quantity' => 2], // Needs 2 buns
        ]);

        // 4. Setup Inventory Layers (Only 1 bun in stock)
        DB::table('inventory_layers')->insert([
            ['business_id' => $businessId, 'product_id' => $bunId, 'remaining_qty' => 1, 'unit_cost' => 0.50],
        ]);

        // 5. Execute Deduction
        $service = new BOMDeductionService(new ConsumeBatchFIFOInventoryAction());
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Insufficient stock for raw material ID {$bunId}");

        $service->deductForOrder($businessId, $burgerId, 1, 999);
    }
}
