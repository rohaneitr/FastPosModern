<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MathIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = \App\Domain\Tenant\Models\Business::factory()->create([
            'name' => 'Math Test Business',
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
            'name' => 'Math Store',
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId, 
            'name' => 'Weighted Widget', 
            'type' => 'single', 
            'sku' => 'W-MATH-1', 
            'created_by' => $this->user->id, 
            'unit_id' => 1
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->productId, 
            'location_id' => $this->locationId, 
            'qty_available' => 10.0000,
        ]);
    }

    public function test_checkout_math_rounding_integrity()
    {
        // 1.5 quantity * 10.33 price = 15.495
        // + 15% tax (2.32425) = 17.81925
        // Expected rounded: 17.82
        $payload = [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'tax_rate' => 0.15, // 15%
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1.5, 'price' => 10.33],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Assert floating point precision
        $this->assertEquals(17.82, $data['final_total'], "Floating point mismatch: Expected 17.82 but got " . $data['final_total']);
    }
}
