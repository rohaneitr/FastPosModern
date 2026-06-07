<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_sell_more_stock_than_available()
    {
        $owner = User::factory()->create();
        $business = Business::create(['name' => 'Concurrency Business', 'owner_id' => $owner->id, 'is_active' => 1]);
        
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
            'name' => 'Limited Product',
            'sku' => 'LIM-01',
            'type' => 'single',
            'unit_id' => $unitId,
            'enable_stock' => 1,
            'created_by' => $owner->id,
        ]);

        $variationId = DB::table('variations')->insertGetId([
            'product_id' => $productId,
            'name' => 'DUMMY',
            'sell_price_inc_tax' => 50.00,
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $productId,
            'variation_id' => $variationId,
            'location_id' => $locationId,
            'qty_available' => 5, // Only 5 in stock
        ]);

        $payload = [
            'location_id' => $locationId,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'tax_rate' => 0,
            'items' => [
                [
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'quantity' => 10, // Requesting 10 (over stock)
                    'price' => 50.00,
                ]
            ]
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', $payload);

        // Expect validation error (422 Unprocessable Entity) or 400 Bad Request
        // The system must reject this entirely to prevent negative inventory
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inventory']);
    }
}
