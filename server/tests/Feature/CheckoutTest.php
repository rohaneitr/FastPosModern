<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $productId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('businesses')->insert(['id' => 1, 'name' => 'Shop', 'is_active' => true]);
        DB::table('locations')->insert(['id' => 1, 'business_id' => 1, 'name' => 'Main']);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Basic', 'price' => 29, 'interval' => 'month']);
        DB::table('subscriptions')->insert(['business_id' => 1, 'plan_id' => 1, 'status' => 'active']);

        $this->user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => 1, 'name' => 'Widget', 'type' => 'single', 'sku' => 'W-001', 'created_by' => $this->user->id,
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->productId, 'location_id' => 1, 'qty_available' => 50,
        ]);
    }

    public function test_checkout_creates_transaction_and_decrements_stock()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/checkout', [
            'location_id' => 1,
            'payment_method' => 'cash',
            'tax_rate' => 0.1,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 3, 'price' => 25.00],
            ],
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['transaction_id', 'invoice_no']);

        // Verify stock decrement
        $stock = DB::table('product_stocks')->where('product_id', $this->productId)->first();
        $this->assertEquals(47, $stock->qty_available);

        // Verify transaction record
        $tx = DB::table('transactions')->where('id', $response->json('transaction_id'))->first();
        $this->assertEquals('sell', $tx->type);
        $this->assertEquals('final', $tx->status);
        $this->assertEquals(1, $tx->business_id);
    }

    public function test_checkout_rejects_insufficient_stock()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/checkout', [
            'location_id' => 1,
            'payment_method' => 'cash',
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 999, 'price' => 10],
            ],
        ]);

        $response->assertStatus(500); // Insufficient stock
        // Stock should remain unchanged
        $stock = DB::table('product_stocks')->where('product_id', $this->productId)->first();
        $this->assertEquals(50, $stock->qty_available);
    }

    public function test_checkout_with_invalid_location_returns_422()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/checkout', [
            'location_id' => 9999,
            'payment_method' => 'cash',
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1, 'price' => 10],
            ],
        ]);

        $response->assertStatus(422);
    }
}
