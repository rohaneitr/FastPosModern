<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_access_other_tenant_product()
    {
        Role::firstOrCreate(['name' => 'BusinessAdmin']);
        
        $ownerA = User::factory()->create();
        $businessA = Business::create(['name' => 'Tenant A', 'owner_id' => $ownerA->id, 'is_active' => 1]);
        DB::table('subscriptions')->insert([
            'business_id' => $businessA->id,
            'plan_id' => 1,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $userA = User::factory()->create(['business_id' => $businessA->id]);
        $userA->assignRole('BusinessAdmin');

        $ownerB = User::factory()->create();
        $businessB = Business::create(['name' => 'Tenant B', 'owner_id' => $ownerB->id, 'is_active' => 1]);
        DB::table('subscriptions')->insert([
            'business_id' => $businessB->id,
            'plan_id' => 1,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $userB = User::factory()->create(['business_id' => $businessB->id]);
        $userB->assignRole('BusinessAdmin');

        $unitId = DB::table('units')->insertGetId([
            'business_id' => $businessA->id,
            'name' => 'Piece',
            'short_name' => 'pc',
            'created_by' => $ownerA->id,
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $businessA->id,
            'name' => 'Product A',
            'sku' => 'PROD-A',
            'type' => 'single',
            'unit_id' => $unitId,
            'enable_stock' => 1,
            'created_by' => $ownerA->id,
        ]);

        $variationId = DB::table('variations')->insertGetId([
            'product_id' => $productId,
            'name' => 'DUMMY',
            'sell_price_inc_tax' => 100.00,
        ]);

        // User B attempts to fetch Product A belonging to Business A
        $response = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/v1/products/{$productId}");

        // Standard tenant isolation global scope should force a 404 Not Found
        // Ensure that Business B is completely unaware of Business A's assets
        $response->assertStatus(404);
    }
}
