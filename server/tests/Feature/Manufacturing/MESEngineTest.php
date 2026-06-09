<?php

namespace Tests\Feature\Manufacturing;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Modules\Manufacturing\Services\ProductionOrderManager;
use App\Modules\Manufacturing\Exceptions\InsufficientStockException;

class MESEngineTest extends TestCase
{
    use RefreshDatabase;

    protected int $businessId;
    protected int $cpuId;
    protected int $pcId;
    protected int $orderId1;
    protected int $orderId2;
    protected int $orderIdScrap;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup base data
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Intel Fab', 'created_at' => now(), 'updated_at' => now()
        ]);

        $this->cpuId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId, 'name' => 'Raw CPU Die', 'type' => 'single', 'created_at' => now(), 'updated_at' => now()
        ]);

        $this->pcId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId, 'name' => 'Finished CPU', 'type' => 'single', 'created_at' => now(), 'updated_at' => now()
        ]);

        // Purchase exactly 10 CPUs at $10.5000 each
        $txId = DB::table('transactions')->insertGetId([
            'business_id' => $this->businessId, 'type' => 'purchase', 'status' => 'final', 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('purchase_lines')->insert([
            'transaction_id' => $txId, 'product_id' => $this->cpuId, 'quantity' => '10.0000', 'quantity_sold' => '0.0000', 'purchase_price' => '10.5000', 'created_at' => now(), 'updated_at' => now()
        ]);

        // Setup Orders
        $this->orderId1 = DB::table('production_orders')->insertGetId([
            'business_id' => $this->businessId, 'reference_no' => 'PO-001', 'finished_product_id' => $this->pcId, 'quantity_planned' => '6.0000', 'status' => 'Processing', 'created_at' => now(), 'updated_at' => now()
        ]);
        DB::table('production_order_lines')->insert([
            'production_order_id' => $this->orderId1, 'raw_material_id' => $this->cpuId, 'quantity_required' => '6.0000'
        ]);

        $this->orderId2 = DB::table('production_orders')->insertGetId([
            'business_id' => $this->businessId, 'reference_no' => 'PO-002', 'finished_product_id' => $this->pcId, 'quantity_planned' => '6.0000', 'status' => 'Processing', 'created_at' => now(), 'updated_at' => now()
        ]);
        DB::table('production_order_lines')->insert([
            'production_order_id' => $this->orderId2, 'raw_material_id' => $this->cpuId, 'quantity_required' => '6.0000'
        ]);
        
        $this->orderIdScrap = DB::table('production_orders')->insertGetId([
            'business_id' => $this->businessId, 'reference_no' => 'PO-003', 'finished_product_id' => $this->pcId, 'quantity_planned' => '5.0000', 'status' => 'Processing', 'overhead_cost' => '50.0000', 'created_at' => now(), 'updated_at' => now()
        ]);
        DB::table('production_order_lines')->insert([
            'production_order_id' => $this->orderIdScrap, 'raw_material_id' => $this->cpuId, 'quantity_required' => '5.0000'
        ]);
    }

    public function test_concurrent_fifo_layer_race_block()
    {
        $manager = new ProductionOrderManager();

        // Order 1 needs 6 CPUs. Available: 10. Should succeed.
        $result1 = $manager->finishProduction($this->orderId1, $this->businessId);
        $this->assertEquals('10.5000', $result1['final_unit_cost']);

        // Assert 6 sold in ledger
        $this->assertEquals('6.0000', DB::table('purchase_lines')->where('product_id', $this->cpuId)->value('quantity_sold'));

        // Order 2 needs 6 CPUs. Available: 4. Should throw exception.
        $this->expectException(InsufficientStockException::class);
        $manager->finishProduction($this->orderId2, $this->businessId);
        
        // Assert inventory was NOT partially deducted
        $this->assertEquals('6.0000', DB::table('purchase_lines')->where('product_id', $this->cpuId)->value('quantity_sold'));
    }

    public function test_scrap_material_cost_accumulation_audit()
    {
        // Add more stock for this test
        $txId = DB::table('transactions')->insertGetId([
            'business_id' => $this->businessId, 'type' => 'purchase', 'status' => 'final', 'transaction_date' => now()
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $txId, 'product_id' => $this->cpuId, 'quantity' => '10.0000', 'quantity_sold' => '0.0000', 'purchase_price' => '10.5000'
        ]);

        $manager = new ProductionOrderManager();

        // Planned Qty: 5. Required Mat: 5. Scrap: 2. Total Mat Consumed: 7.
        // Cost of 7 CPUs @ 10.5 = 73.5.
        // Overhead Cost = 50.0.
        // Total Accumulated Cost = 123.5.
        // Final Unit Cost (Per 5 Planned Output) = 123.5 / 5 = 24.7000.
        
        $scrapPayload = [
            ['raw_material_id' => $this->cpuId, 'qty' => '2.0000', 'reason' => 'Machine Jam']
        ];

        $result = $manager->finishProduction($this->orderIdScrap, $this->businessId, $scrapPayload);

        $this->assertEquals('24.7000', $result['final_unit_cost']);
        $this->assertEquals('123.5000', $result['total_cost']);

        // Assert Scrap Log
        $scrapLog = DB::table('production_scrap_logs')->where('production_order_id', $this->orderIdScrap)->first();
        $this->assertNotNull($scrapLog);
        $this->assertEquals('2.0000', $scrapLog->actual_scrapped_qty);
        
        // Assert Inbound Ledger Lock
        $finishedGoodLayer = DB::table('purchase_lines')->where('product_id', $this->pcId)->first();
        $this->assertNotNull($finishedGoodLayer);
        $this->assertEquals('5.0000', $finishedGoodLayer->quantity);
        $this->assertEquals('24.7000', $finishedGoodLayer->purchase_price);
    }
}
