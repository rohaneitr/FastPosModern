<?php

namespace Tests\Feature\CRM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use App\Modules\Sales\Jobs\SendInvoiceNotificationJob;

class CustomerLoyaltyWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // create basic setup
        $this->businessId = DB::table('businesses')->insertGetId(['name' => 'Test Business', 'created_at' => now(), 'updated_at' => now()]);
        $this->userId = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'),
            'business_id' => $this->businessId, 'role' => 'BusinessAdmin', 'created_at' => now(), 'updated_at' => now()
        ]);
        $this->contactId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId, 'type' => 'customer', 'name' => 'Test Contact', 
            'email' => 'contact@test.com', 'mobile' => '1234567890', 'created_at' => now(), 'updated_at' => now()
        ]);
        $this->locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId, 'name' => 'HQ', 'created_at' => now(), 'updated_at' => now()
        ]);
        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId, 'name' => 'Product 1', 'type' => 'single',
            'created_at' => now(), 'updated_at' => now()
        ]);
    }

    public function test_wallet_double_spend_guard()
    {
        DB::table('customer_wallets')->insert([
            'business_id' => $this->businessId,
            'contact_id' => $this->contactId,
            'balance' => 500.0000,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $payload = [
            'location_id' => $this->locationId,
            'contact_id' => $this->contactId,
            'payment_method' => 'store_credit',
            'amount_paid' => 400.00,
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1, 'price' => 400.00]
            ]
        ];

        $user = User::find($this->userId);
        
        $response1 = $this->actingAs($user)->postJson('/api/v1/checkout', $payload);
        $response1->assertStatus(200);

        // Try second time
        $response2 = $this->actingAs($user)->postJson('/api/v1/checkout', $payload);
        $response2->assertStatus(500); // Exception is thrown
        $this->assertStringContainsString('Location Overdraft', $response2->json('message'));
        
        $wallet = DB::table('customer_wallets')->where('contact_id', $this->contactId)->first();
        $this->assertEquals(100, $wallet->balance);
    }

    public function test_return_point_eviction_proof()
    {
        $payload = [
            'location_id' => $this->locationId,
            'contact_id' => $this->contactId,
            'payment_method' => 'cash',
            'amount_paid' => 1000.00,
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1, 'price' => 1000.00]
            ]
        ];

        $user = User::find($this->userId);
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', $payload);
        $response->assertStatus(200);

        $txId = $response->json('transaction_id');
        $ledger = DB::table('loyalty_point_ledgers')->where('contact_id', $this->contactId)->first();
        $this->assertEquals(10, $ledger->points_earned);
        $this->assertEquals(10, $ledger->running_balance);

        // RMA
        $rmaPayload = [
            'transaction_id' => $txId,
            'return_amount' => 1000.00,
            'refund_method' => 'cash',
            'lines' => [
                ['product_id' => $this->productId, 'quantity' => 1]
            ]
        ];
        $responseRma = $this->actingAs($user)->postJson('/api/v1/sales/return', $rmaPayload);
        $responseRma->assertStatus(200);

        $lastLedger = DB::table('loyalty_point_ledgers')->where('contact_id', $this->contactId)->orderByDesc('id')->first();
        $this->assertEquals(10, $lastLedger->points_redeemed);
        $this->assertEquals(0, $lastLedger->running_balance);
    }

    public function test_queue_isolation_proving()
    {
        Queue::fake();

        $payload = [
            'location_id' => $this->locationId,
            'contact_id' => $this->contactId,
            'payment_method' => 'cash',
            'amount_paid' => 100.00,
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1, 'price' => 100.00]
            ]
        ];

        $user = User::find($this->userId);
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', $payload);
        $response->assertStatus(200);

        Queue::assertPushed(SendInvoiceNotificationJob::class);
    }
}
