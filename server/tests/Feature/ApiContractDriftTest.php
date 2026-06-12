<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;

use Illuminate\Foundation\Testing\WithoutMiddleware;

class ApiContractDriftTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected $user;
    protected $productId;
    protected $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET session_replication_role = replica;');

        DB::table('businesses')->insert(['id' => $this->businessId, 'name' => 'Shop', 'is_active' => true, 'owner_id' => 999, 'settings' => json_encode(['pos_enforce_strict_cash_control' => false])]);

        $userId = DB::table('users')->insertGetId([
            'id' => 999,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'allow_login' => true,
            'business_id' => $this->businessId,
        ]);
        $this->user = User::find($userId);

        DB::statement('SET session_replication_role = origin;');
        
        $this->user->business_id = $this->businessId;
        $this->user->save();

        DB::table('locations')->insertOrIgnore(['id' => 1, 'business_id' => $this->businessId, 'name' => 'Main']);
        DB::table('locations')->insertOrIgnore(['id' => 2, 'business_id' => $this->businessId, 'name' => 'Warehouse']);
        DB::table('plans')->insertOrIgnore(['id' => 1, 'name' => 'Basic', 'price' => 29, 'interval' => 'month']);
        DB::table('subscriptions')->insertOrIgnore(['business_id' => $this->businessId, 'plan_id' => 1, 'status' => 'active']);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId, 'name' => 'Widget', 'sku' => 'W-001', 'purchase_price' => 10, 'selling_price' => 25
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->productId, 'location_id' => 1, 'qty_available' => 50,
        ]);

        $accounts = [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2200', 'name' => 'Tax Payable', 'type' => 'liability'],
            ['code' => '4000', 'name' => 'Sales', 'type' => 'revenue'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'Discount', 'type' => 'expense'],
            ['code' => '5200', 'name' => 'Cost Variance', 'type' => 'expense'],
            ['code' => '5300', 'name' => 'Cash Discrepancy', 'type' => 'expense'],
        ];

        foreach ($accounts as $acc) {
            \App\Models\ChartOfAccount::forceCreate([
                'business_id' => $this->businessId,
                'name' => $acc['name'],
                'type' => $acc['type'],
                'code' => $acc['code'],
                'is_active' => true
            ]);
        }
        
        dump('COA Count immediately after create: ' . \App\Models\ChartOfAccount::count());

        \Illuminate\Support\Facades\Schema::create('audit_logs', function ($table) {
            $table->id();
            $table->integer('business_id')->nullable();
            $table->integer('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_name')->nullable();
            $table->string('event')->nullable();
            $table->string('action')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable();
            $table->integer('subject_id')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('device_hash')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();
        });

        DB::table('inventory_layers')->insert([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'original_qty' => 100,
            'remaining_qty' => 50,
            'unit_cost' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->mock(\App\Modules\Finance\Services\DoubleEntryEngine::class, function ($mock) {
            $mock->shouldReceive('recordEntry')->andReturn(new \App\Models\JournalEntry());
        });
        $this->mock(\App\Modules\Security\Services\ForensicAuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturnTrue();
        });
    }

    public function test_checkout_validates_serial_numbers_and_fractional_ratios()
    {
        // Add serial numbers to inventory
        DB::table('inventory_item_serials')->insert([
            ['business_id' => $this->businessId, 'product_id' => $this->productId, 'serial_number' => 'SN001', 'status' => 'In_Stock'],
            ['business_id' => $this->businessId, 'product_id' => $this->productId, 'serial_number' => 'SN002', 'status' => 'In_Stock'],
        ]);

        $reflection = new \ReflectionClass(\App\Modules\Finance\Services\TenantAccountResolver::class);
        $property = $reflection->getProperty('resolvedCache');
        $property->setAccessible(true);
        $property->setValue(null, [
            "1_1000" => 1,
            "1_1200" => 2,
            "1_1300" => 3,
            "1_2000" => 4,
            "1_2200" => 5,
            "1_4000" => 6,
            "1_5000" => 7,
            "1_5100" => 8,
            "1_5200" => 9,
            "1_5300" => 10,
        ]);
        
        $response = $this->actingAs($this->user)->postJson('/api/v1/checkout', [
            'location_id' => 1,
            'payment_method' => 'cash',
            'tax_rate' => 0.1,
            'items' => [
                [
                    'product_id' => $this->productId, 
                    'quantity' => 2, 
                    'price' => 25.00,
                    'serial_numbers' => ['SN001', 'SN002'],
                    'fractional_ratio' => 1,
                    'dosage_instructions' => 'Take twice daily'
                ],
            ],
        ]);
        
        if ($response->status() !== 201) {
            echo "\n\nCRITICAL API ERROR:\n";
            echo $response->json('message') . "\n";
            echo $response->json('error') . "\n\n";
        }

        $response->assertStatus(201);

        $transactionId = $response->json('transaction_id');
        
        // Verify serial numbers were attached
        $serialsLinked = DB::table('transaction_item_serials')
            ->join('transaction_lines', 'transaction_item_serials.transaction_item_id', '=', 'transaction_lines.id')
            ->where('transaction_lines.transaction_id', $transactionId)
            ->count();
            
        $this->assertEquals(2, $serialsLinked, "Serial numbers were dropped by validation!");
    }

    public function test_inventory_adjust_with_decrease_adjustment_type()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/inventory/adjust', [
            'location_id' => 1,
            'product_id' => $this->productId,
            'quantity' => 5, // Positive quantity
            'adjustment_type' => 'decrease', // Indicates decrease
            'reason' => 'Damaged goods'
        ]);
        
        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);

        // Expected stock should be 50 - 5 = 45.
        // If it fails, it will be 50 + 5 = 55.
        $stock = DB::table('product_stocks')->where('product_id', $this->productId)->where('location_id', 1)->first();
        $this->assertEquals(45, $stock->qty_available, "Stock adjustment did not decrease correctly based on adjustment_type!");
    }

    public function test_inventory_transfer_with_note()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/inventory/transfer', [
            'product_id' => $this->productId,
            'from_location_id' => 1,
            'to_location_id' => 2,
            'quantity' => 10,
            'note' => 'Transferring excess stock'
        ]);
        
        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);

        // Check if the reason was saved in the stock_adjustments audit log
        $audit = DB::table('stock_adjustments')->where('product_id', $this->productId)->orderBy('id', 'desc')->first();
        
        $this->assertNotNull($audit);
        $this->assertStringContainsString('Transferring excess stock', $audit->reason, "The 'note' field was not mapped to 'reason'!");
    }
}
