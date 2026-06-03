<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CoreIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed basic dependencies manually for isolation
        DB::table('businesses')->insert([
            ['id' => 1, 'name' => 'Tenant A', 'is_active' => true],
            ['id' => 2, 'name' => 'Tenant B', 'is_active' => true]
        ]);

        DB::table('locations')->insert([
            ['id' => 1, 'business_id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'business_id' => 2, 'name' => 'Store B']
        ]);

        // Default plans and subscriptions to bypass CheckSubscription middleware
        DB::table('plans')->insert([
            ['id' => 1, 'name' => 'Basic', 'price' => 29, 'interval' => 'month']
        ]);
        
        DB::table('subscriptions')->insert([
            ['business_id' => 1, 'plan_id' => 1, 'status' => 'active'],
            ['business_id' => 2, 'plan_id' => 1, 'status' => 'active']
        ]);
    }

    public function test_tenant_isolation_prevents_accessing_other_business_data()
    {
        // Create user for Tenant A
        $userA = User::factory()->create(['business_id' => 1]);

        // Create product in Tenant B
        $productId = DB::table('products')->insertGetId([
            'business_id' => 2,
            'name' => 'Tenant B Product',
            'type' => 'single',
            'sku' => 'SKU-B',
            'created_by' => $userA->id
        ]);

        $response = $this->actingAs($userA)->getJson('/api/v1/products/' . $productId);
        $response->assertStatus(404); // Should not find it because global scope filters it
    }

    public function test_offline_sync_push_idempotency_and_stock_deduction()
    {
        $user = User::factory()->create(['business_id' => 1]);
        
        // Setup product & stock for checkout
        $productId = DB::table('products')->insertGetId([
            'business_id' => 1, 'name' => 'Test Product', 'type' => 'single', 'sku' => 'TEST-01', 'created_by' => $user->id
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $productId, 'location_id' => 1, 'qty_available' => 10
        ]);

        $payload = [
            'transactions' => [
                [
                    'invoice_no' => 'OFFLINE-INV-1',
                    'location_id' => 1,
                    'transaction_date' => Carbon::now()->subHour()->toDateTimeString(),
                    'payment_method' => 'cash',
                    'tax_rate' => 0.1,
                    'items' => [
                        ['product_id' => $productId, 'quantity' => 2, 'price' => 100]
                    ]
                ]
            ]
        ];

        // 1st Push
        $response = $this->actingAs($user)->postJson('/api/v1/mobile/sync/push', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('synced_count', 1);

        // Check Stock
        $stock = DB::table('product_stocks')->where('product_id', $productId)->first();
        $this->assertEquals(8, $stock->qty_available);

        // 2nd Push (Idempotency check - same payload)
        $response2 = $this->actingAs($user)->postJson('/api/v1/mobile/sync/push', $payload);
        $response2->assertStatus(200);
        $response2->assertJsonPath('synced_count', 0); // Should skip because invoice_no already exists

        // Check Stock again (should NOT deduct twice)
        $stock2 = DB::table('product_stocks')->where('product_id', $productId)->first();
        $this->assertEquals(8, $stock2->qty_available);
    }
}
