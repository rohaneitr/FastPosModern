<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = \App\Domain\Tenant\Models\Business::factory()->create([
            'name' => 'Inventory Business',
            'owner_id' => 1,
            'is_active' => true,
        ])->id;

        $this->user = User::factory()->create([
            'id' => 1,
            'business_id' => $this->businessId,
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $this->user->assignRole('BusinessAdmin');

        $this->locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Main Warehouse',
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'type' => 'single',
            'created_by' => $this->user->id,
            'unit_id' => 1,
        ]);
    }

    public function test_can_list_inventory_stock()
    {
        DB::table('product_stocks')->insert([
            'product_id' => $this->productId,
            'location_id' => $this->locationId,
            'qty_available' => 100,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/inventory/stock');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.qty_available', 100)
                 ->assertJsonPath('data.0.product_name', 'Test Product');
    }

    public function test_can_adjust_stock()
    {
        $payload = [
            'location_id' => $this->locationId,
            'product_id' => $this->productId,
            'quantity' => 25,
            'reason' => 'Inventory Audit',
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/inventory/adjust', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $this->productId,
            'location_id' => $this->locationId,
            'qty_available' => 25,
        ]);
    }
}
