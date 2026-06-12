<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction;
use Brick\Math\BigDecimal;
use Carbon\Carbon;

class PharmacyInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fefo_pharmacy_deduction()
    {
        // 1. Setup Business & Product
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Pharmacy Tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Paracetamol 500mg',
            'sku' => 'MED-PARA-500',
            'purchase_price' => '1.00',
            'sell_price' => '2.50',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Add two batches of medicine
        // Batch A: Arrived first, but expires next year (Fresh)
        $freshBatchId = DB::table('inventory_layers')->insertGetId([
            'business_id' => $businessId,
            'product_id' => $productId,
            'original_qty' => '100.0000',
            'remaining_qty' => '100.0000',
            'unit_cost' => '1.0000',
            'expiry_date' => Carbon::now()->addYear()->toDateString(),
            'lot_number' => 'LOT-FRESH',
            'created_at' => Carbon::now()->subDays(10), // Older in system
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // Batch B: Arrived today, but expires next month (Near Expiry)
        $expiringBatchId = DB::table('inventory_layers')->insertGetId([
            'business_id' => $businessId,
            'product_id' => $productId,
            'original_qty' => '50.0000',
            'remaining_qty' => '50.0000',
            'unit_cost' => '1.2000',
            'expiry_date' => Carbon::now()->addMonth()->toDateString(),
            'lot_number' => 'LOT-EXPIRING',
            'created_at' => Carbon::now(), // Newer in system
            'updated_at' => Carbon::now(),
        ]);

        // 3. Execute a sale of 60 units
        // The system SHOULD deduct 50 from the expiring batch first (FEFO),
        // and then 10 from the fresh batch.
        $action = new ConsumeBatchFIFOInventoryAction();
        $cogsMap = $action->execute($businessId, [
            $productId => 60
        ]);

        // COGS should be: (50 * 1.20) + (10 * 1.00) = 60.00 + 10.00 = 70.00
        $this->assertEquals('70.0000', $cogsMap[$productId]);

        // 4. Assert stock deducted from batch nearing expiry first (FEFO)
        $expiringBatch = DB::table('inventory_layers')->where('id', $expiringBatchId)->first();
        $freshBatch = DB::table('inventory_layers')->where('id', $freshBatchId)->first();

        $this->assertEquals('0.0000', $expiringBatch->remaining_qty, 'Expiring batch should be fully depleted.');
        $this->assertEquals('90.0000', $freshBatch->remaining_qty, 'Fresh batch should only lose 10 units.');
    }
}
