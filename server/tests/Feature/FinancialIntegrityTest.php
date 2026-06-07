<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_calculates_discounts_and_taxes_accurately()
    {
        $owner = User::factory()->create();
        $business = Business::create(['name' => 'Test Business', 'owner_id' => $owner->id, 'is_active' => 1]);
        
        DB::table('subscriptions')->insert([
            'business_id' => $business->id,
            'plan_id' => 1,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $user = User::factory()->create(['business_id' => $business->id]);
        $user->assignRole('BusinessAdmin');

        $unitId = DB::table('units')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Piece',
            'short_name' => 'pc',
            'created_by' => $owner->id,
        ]);

        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Main Store',
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Test Product',
            'sku' => 'TEST-01',
            'type' => 'single',
            'unit_id' => $unitId,
            'enable_stock' => 1,
            'created_by' => $owner->id,
        ]);

        $variationId = DB::table('variations')->insertGetId([
            'product_id' => $productId,
            'name' => 'DUMMY',
            'sell_price_inc_tax' => 100.00,
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $productId,
            'variation_id' => $variationId,
            'location_id' => $locationId,
            'qty_available' => 50,
        ]);

        $payload = [
            'location_id' => $locationId,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_amount' => 10.00, // $10 off
            'tax_rate' => 0.05, // 5% tax
            'items' => [
                [
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'quantity' => 2,
                    'price' => 100.00, // Subtotal = 200
                ]
            ]
        ];

        // Calculation Expected:
        // Initial Subtotal: 200
        // Discount: 10
        // Pre-tax Subtotal: 190
        // Tax (5%): 9.50
        // Final Total: 199.50

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);
        // Assert JSON payload returns precise financial breakdown
        $response->assertJsonPath('final_total', 199.50);
        $response->assertJsonPath('tax', 9.50);
        $response->assertJsonPath('discount', 10);

        // Verify stock was successfully deducted
        $stock = DB::table('product_stocks')->where('product_id', $productId)->first();
        $this->assertEquals(48, $stock->qty_available);
    }
}
