<?php

namespace Tests\Feature\Finance;

use App\Modules\Inventory\Actions\ConsumeFIFOInventoryAction;
use App\Modules\Inventory\Models\InventoryLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FIFOInventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $productId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $adminId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'fifo_admin@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'FIFO Valuations', 
            'owner_id' => $adminId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Product X',
            'sku' => 'PRD-X',
            'selling_price' => '25.0000',
            'purchase_price' => '10.0000', // Base cost for fallbacks
            'current_stock' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fifo_inventory_layering_and_dynamic_cogs_calculation()
    {
        // 1. Inbound Layer 1: 10 Units @ $10.00
        InventoryLayer::create([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'original_qty' => '10.0000',
            'remaining_qty' => '10.0000',
            'unit_cost' => '10.0000',
            'created_at' => now()->subDays(10), // Older
        ]);

        // 2. Inbound Layer 2: 10 Units @ $15.00
        $layer2 = InventoryLayer::create([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'original_qty' => '10.0000',
            'remaining_qty' => '10.0000',
            'unit_cost' => '15.0000',
            'created_at' => now()->subDays(5), // Newer
        ]);

        // 3. Outbound Execution: Sell 12 Units
        // It should consume all 10 of Layer 1 ($100), and 2 of Layer 2 ($30). Total COGS = 130.0000.
        $action = new \App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction();
        
        DB::transaction(function () use ($action) {
            $cogsMap = $action->execute($this->businessId, [$this->productId => '12.0000']);
            $this->assertEquals('130.0000', $cogsMap[$this->productId]);
        });

        // 4. Assert Layer State after consumption
        $layers = InventoryLayer::where('product_id', $this->productId)->orderBy('created_at', 'asc')->get();
        
        $this->assertCount(2, $layers);
        
        // Layer 1 must be exactly zeroed out
        $this->assertEquals('0.0000', $layers[0]->remaining_qty);
        
        // Layer 2 must have exactly 8 units remaining
        $this->assertEquals('8.0000', $layers[1]->remaining_qty);
    }

    public function test_deterministic_lock_ordering_prevents_deadlocks()
    {
        $products = [];
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $pId = DB::table('products')->insertGetId([
                'business_id' => $this->businessId,
                'name' => "Product $i",
                'sku' => "PRD-B$i",
                'selling_price' => '25.0000',
                'purchase_price' => '10.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[] = $pId;
            
            InventoryLayer::create([
                'business_id' => $this->businessId,
                'product_id' => $pId,
                'original_qty' => '10.0000',
                'remaining_qty' => '10.0000',
                'unit_cost' => '10.0000',
            ]);
        }

        // Shuffle the IDs to simulate a random cart order
        $shuffledIds = $ids;
        shuffle($shuffledIds);
        
        foreach ($shuffledIds as $id) {
            $products[$id] = '5.0000';
        }

        DB::enableQueryLog();

        $action = new \App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction();
        DB::transaction(function () use ($action, $products) {
            $action->execute($this->businessId, $products);
        });

        $queryLog = DB::getQueryLog();
        $lockQueries = array_filter($queryLog, function ($q) {
            return strpos(strtolower($q['query']), 'for update') !== false;
        });

        $this->assertCount(1, $lockQueries);
        
        $lockQuery = reset($lockQueries);
        $bindings = $lockQuery['bindings'];
        
        // The last binding is '0' for the where('remaining_qty', '>', 0) clause.
        // We pop it off to only assert the product_id whereIn bindings.
        array_pop($bindings);
        
        // Assert that the whereIn bindings are explicitly sorted in ascending order
        $sortedBindings = $bindings;
        sort($sortedBindings);
        $this->assertEquals($sortedBindings, $bindings);
    }

    public function test_return_balance_restores_fifo_layer()
    {
        $action = new \App\Modules\Inventory\Actions\RestoreFIFOLayerAction();
        $action->execute($this->businessId, $this->productId, '3.0000');

        $layer = InventoryLayer::where('product_id', $this->productId)->first();
        
        $this->assertNotNull($layer);
        $this->assertEquals('3.0000', $layer->remaining_qty);
        $this->assertEquals('10.0000', $layer->unit_cost); // Tied to product base purchase_price
    }

    public function test_negative_stock_outbreak_creates_fallback_layer()
    {
        // No existing layers. Sell 5 units.
        $action = new \App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction();
        
        DB::transaction(function () use ($action) {
            $cogsMap = $action->execute($this->businessId, [$this->productId => '5.0000']);
            // 5 units * $10 base cost = 50.0000 COGS
            $this->assertEquals('50.0000', $cogsMap[$this->productId]);
        });

        // Assert negative layer creation
        $layers = InventoryLayer::where('product_id', $this->productId)->get();
        $this->assertCount(1, $layers);
        $this->assertEquals('-5.0000', $layers[0]->remaining_qty);
        $this->assertEquals('10.0000', $layers[0]->unit_cost);
    }
    public function test_automated_negative_stock_true_up_engine()
    {
        // Setup Chart of Accounts
        $accountTypes = [
            \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE => 'expense',
            \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY => 'asset'
        ];
        foreach ($accountTypes as $code => $type) {
            DB::table('chart_of_accounts')->insert([
                'business_id' => $this->businessId,
                'name' => 'Account ' . $code,
                'code' => $code,
                'type' => $type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE);
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);

        // 1. Force a negative stock sale of 5 units.
        $action = new \App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction();
        DB::transaction(function () use ($action) {
            $action->execute($this->businessId, [$this->productId => '5.0000']);
        });

        // Verify negative layer exists
        $layers = InventoryLayer::where('product_id', $this->productId)->get();
        $this->assertCount(1, $layers);
        $this->assertEquals('-5.0000', $layers[0]->remaining_qty);
        $this->assertEquals('10.0000', $layers[0]->unit_cost); // Fallback base cost

        // 2. Register PO of 10 units at $12.0000
        $trueUpAction = new \App\Modules\Inventory\Actions\ReconcileNegativeLayersAction();
        $purchaseId = 999;
        
        $remainingQty = $trueUpAction->execute($this->businessId, $this->productId, '10.0000', '12.0000', $purchaseId);
        
        // Assert True-Up Logic
        $this->assertEquals('5.0000', $remainingQty); // 10 incoming - 5 negative debt = 5 remaining for new layer

        // Assert Negative layer is extinguished (remaining_qty = 0)
        $layers = InventoryLayer::where('product_id', $this->productId)->orderBy('created_at', 'asc')->get();
        $this->assertEquals('0.0000', $layers[0]->remaining_qty);

        // Verify General Ledger Cost Variance
        $varianceAccount = \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE);
        $inventoryAccount = \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);
        
        $journalEntry = \App\Models\JournalEntry::where('reference_id', $purchaseId)->where('reference_type', 'purchase_trueup')->first();
        $this->assertNotNull($journalEntry);

        // Variance = (12.0000 - 10.0000) * 5 extinguished = 10.0000
        $varianceDebit = \App\Models\JournalLine::where('journal_entry_id', $journalEntry->id)
            ->where('chart_of_account_id', $varianceAccount)
            ->where('type', 'debit')
            ->first();
            
        $this->assertNotNull($varianceDebit);
        $this->assertEquals('10.0000', $varianceDebit->amount);
    }

    public function test_new_tenant_onboarding_seeds_cost_variance_account()
    {
        $userId = DB::table('users')->insertGetId([
            'first_name' => 'Test Owner',
            'email' => 'testowner' . mt_rand() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Create a fresh business (triggers BusinessObserver)
        $newBusinessId = DB::table('businesses')->insertGetId([
            'name' => 'Fresh Tenant LLC', 
            'owner_id' => $userId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Manually trigger the observer since DB::table doesn't dispatch Eloquent events
        $businessModel = \App\Modules\Tenant\Models\Business::find($newBusinessId);
        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created($businessModel);

        // 2. Resolve the Cost Variance account
        $varianceAccount = \App\Modules\Finance\Services\TenantAccountResolver::resolve($newBusinessId, \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE);
        
        $this->assertNotNull($varianceAccount);
        $this->assertIsNumeric($varianceAccount);

        $accountName = DB::table('chart_of_accounts')->where('id', $varianceAccount)->value('name');
        $this->assertEquals('Cost Variance', $accountName);
    }

    public function test_partial_true_up_stress_test()
    {
        // Setup Chart of Accounts
        $accountTypes = [
            \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE => 'expense',
            \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY => 'asset'
        ];
        foreach ($accountTypes as $code => $type) {
            DB::table('chart_of_accounts')->insert([
                'business_id' => $this->businessId,
                'name' => 'Account ' . $code,
                'code' => $code,
                'type' => $type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE);
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);

        // 1. Force a negative stock sale of 10 units.
        $action = new \App\Modules\Inventory\Actions\ConsumeBatchFIFOInventoryAction();
        DB::transaction(function () use ($action) {
            $action->execute($this->businessId, [$this->productId => '10.0000']);
        });

        // Verify -10.0000 layer
        $layers = InventoryLayer::where('product_id', $this->productId)->get();
        $this->assertEquals('-10.0000', $layers[0]->remaining_qty);

        // 2. Register PO of 6 units at $12.0000
        $trueUpAction = new \App\Modules\Inventory\Actions\ReconcileNegativeLayersAction();
        $purchaseId = 777;
        
        $remainingQty = $trueUpAction->execute($this->businessId, $this->productId, '6.0000', '12.0000', $purchaseId);
        
        // Assert True-Up Logic
        $this->assertEquals('0.0000', $remainingQty); // 6 incoming - 10 debt = 0 remaining for new layer

        // Assert Negative layer is PARTIALLY extinguished (-10 + 6 = -4)
        $layers = InventoryLayer::where('product_id', $this->productId)->orderBy('created_at', 'asc')->get();
        $this->assertEquals('-4.0000', $layers[0]->remaining_qty);

        // Verify General Ledger Cost Variance for 6 units only!
        // Variance = (12.0000 - 10.0000) * 6 extinguished = 12.0000
        $varianceAccount = \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::COST_VARIANCE);
        
        $journalEntry = \App\Models\JournalEntry::where('reference_id', $purchaseId)->where('reference_type', 'purchase_trueup')->first();
        $this->assertNotNull($journalEntry);

        $varianceDebit = \App\Models\JournalLine::where('journal_entry_id', $journalEntry->id)
            ->where('chart_of_account_id', $varianceAccount)
            ->where('type', 'debit')
            ->first();
            
        $this->assertNotNull($varianceDebit);
        $this->assertEquals('12.0000', $varianceDebit->amount);
    }
}
