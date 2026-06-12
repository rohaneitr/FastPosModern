<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;

class POSCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles are seeded (Spatie expects them)
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_pos_checkout_successfully_deducts_inventory_and_generates_invoice()
    {
        // 1. Setup Tenant and Environment
        $business = Business::create([
            'name' => 'Test Retail',
            'subdomain' => 'testretail',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'business_id' => $business->id,
            'password' => bcrypt('password123'),
        ]);

        // 2. Setup Location & Product
        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Main Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'type' => 'single',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add 50 qty in stock
        DB::table('product_stocks')->insert([
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_available' => '50.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Setup chart of accounts for Double-Entry hook
        // Mocking the required default accounts
        foreach (['Cash', 'Accounts Receivable', 'Sales', 'Tax Payable', 'Discount', 'Cost of Goods Sold', 'Inventory'] as $acc) {
            DB::table('chart_of_accounts')->insert([
                'business_id' => $business->id,
                'name' => $acc,
                'code' => strtoupper(substr($acc, 0, 4)) . rand(100, 999),
                'account_type' => 'asset',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Add a FIFO layer to satisfy ConsumeBatchFIFOInventoryAction
        DB::table('inventory_layers')->insert([
            'business_id' => $business->id,
            'product_id' => $productId,
            'location_id' => $locationId,
            'transaction_line_id' => null,
            'qty_purchased' => '50.0000',
            'qty_remaining' => '50.0000',
            'unit_cost' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        // Bypass security lock for test
        $business->update(['settings' => json_encode(['pos_enforce_strict_cash_control' => false])]);

        // 3. Perform POS Checkout Action
        $payload = [
            'location_id' => $locationId,
            'document_type' => 'Invoice',
            'payment_method' => 'cash',
            'amount_paid' => 110.00,
            'tax_rate' => 0.10, // 10%
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 2,
                    'price' => 50.00 // Subtotal 100
                ]
            ]
        ];

        // Ensure routes are available
        $response = $this->postJson('/api/v1/sales/checkout', $payload);

        // 4. Assertions
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'transaction_id',
                     'invoice_no',
                     'subtotal',
                     'tax',
                     'final_total'
                 ]);

        $response->assertJsonPath('subtotal', 100.00);
        $response->assertJsonPath('tax', 10.00);
        $response->assertJsonPath('final_total', 110.00);

        // Verify Inventory Deduction
        $stock = DB::table('product_stocks')->where('product_id', $productId)->first();
        $this->assertEquals('48.0000', $stock->qty_available);

        // Verify Transaction Record
        $transaction = DB::table('transactions')->where('id', $response->json('transaction_id'))->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('110.0000', $transaction->final_total);
    }
}
