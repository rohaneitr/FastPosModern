<?php

namespace Tests\Feature\Inventory;

use App\Domain\IAM\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockAdjustmentRaceConditionTest extends TestCase
{
    // We must use DatabaseTruncation, NOT RefreshDatabase.
    // RefreshDatabase uses DB transactions, so child processes 
    // spawned by Concurrency::run() won't see uncommitted test data.
    use DatabaseTruncation;

    public function test_concurrent_stock_adjustment_prevents_insert_anomaly()
    {
        // 1. Setup Tenant Context
        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'username' => 'testowner' . uniqid(),
            'email' => 'owner' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Test Business',
            'owner_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $ownerId)->update(['business_id' => $businessId]);

        $user = User::find($ownerId);

        // Create API Token for cross-process authentication
        $token = $user->createToken('test-token')->plainTextToken;

        // 2. Setup Database State (Product & Location without any stock records)
        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Main Warehouse (Concurrency Test)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Race Condition Product',
            'sku' => 'RC-TEST-001',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ensure no stock exists initially
        $this->assertDatabaseMissing('product_stocks', [
            'product_id' => $productId,
            'location_id' => $locationId,
        ]);

        $results = Concurrency::run([
            function () use ($businessId, $ownerId, $productId, $locationId) {
                config([
                    'database.default' => 'pgsql',
                    'database.connections.pgsql.database' => 'fastpos'
                ]);
                $action = new \App\Modules\Inventory\Actions\AdjustStockAction();
                try {
                    $result = $action->execute($businessId, $ownerId, $productId, $locationId, 10, 'Race 1');
                    return ['status' => 200, 'body' => $result];
                } catch (\Exception $e) {
                    return ['status' => 500, 'body' => $e->getMessage()];
                }
            },
            function () use ($businessId, $ownerId, $productId, $locationId) {
                config([
                    'database.default' => 'pgsql',
                    'database.connections.pgsql.database' => 'fastpos'
                ]);
                $action = new \App\Modules\Inventory\Actions\AdjustStockAction();
                try {
                    $result = $action->execute($businessId, $ownerId, $productId, $locationId, 10, 'Race 2');
                    return ['status' => 200, 'body' => $result];
                } catch (\Exception $e) {
                    return ['status' => 500, 'body' => $e->getMessage()];
                }
            },
        ]);

        // Both requests should ideally return 200 OK.
        dump($results);
        $statuses = array_column($results, 'status');
        $this->assertContains(200, $statuses, "At least one request should succeed.");

        // 4. Assert Database Integrity (The Core Verification)
        // If the race condition exists, this assertion will fail because 
        // there will be 2 rows in the product_stocks table instead of 1.
        $stockCount = DB::table('product_stocks')
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->count();

        $this->assertEquals(1, $stockCount, "Race condition detected! Multiple stock records created for the same product and location.");

        // If data integrity is maintained, the final quantity should be 20.
        $finalStock = DB::table('product_stocks')
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();

        $this->assertEquals(20, $finalStock->qty_available, "Stock quantity does not match the sum of concurrent adjustments.");
    }
}
