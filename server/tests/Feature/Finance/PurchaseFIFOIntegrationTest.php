<?php

namespace Tests\Feature\Finance;

use App\Modules\Inventory\Models\InventoryLayer;
use App\Modules\Procurement\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class PurchaseFIFOIntegrationTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected $businessId;
    protected $productId;
    protected $supplierId;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $adminId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'fifo_admin2@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'FIFO Valuations', 
            'owner_id' => $adminId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $adminId)->update(['business_id' => $this->businessId]);
        $this->adminUser = \App\Domain\IAM\Models\User::find($adminId);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'sanctum']);
        $this->adminUser->assignRole('BusinessAdmin');

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Product Y',
            'sku' => 'PRD-Y',
            'selling_price' => '25.0000',
            'purchase_price' => '10.0000',
            'current_stock' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->supplierId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'supplier',
            'name' => 'Supplier Co',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountTypes = [
            \App\Modules\Finance\Services\TenantAccountResolver::CASH => 'asset',
            \App\Modules\Finance\Services\TenantAccountResolver::AP => 'liability',
            \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY => 'asset'
        ];
        
        foreach ($accountTypes as $code => $type) {
            DB::table('chart_of_accounts')->insert([
                'business_id' => $this->businessId,
                'name' => 'Account ' . $code,
                'code' => $code,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH);
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::AP);
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::INVENTORY);
    }

    public function test_purchasing_automatically_generates_strict_fifo_layers()
    {
        $payload = [
            'contact_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'status' => 'received',
            'amount_paid' => '0',
            'lines' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => '15',
                    'purchase_price' => '12.50'
                ]
            ]
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/purchases', $payload);

        if ($response->status() !== 201) {
            dump($response->json());
        }
        $response->assertStatus(201);

        // Assert Layer Generation
        $layers = InventoryLayer::where('business_id', $this->businessId)
            ->where('product_id', $this->productId)
            ->get();

        $this->assertCount(1, $layers);
        $this->assertEquals('15.0000', $layers[0]->original_qty);
        $this->assertEquals('15.0000', $layers[0]->remaining_qty);
        $this->assertEquals('12.5000', $layers[0]->unit_cost);
    }
}
