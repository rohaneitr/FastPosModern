<?php

namespace Tests\Feature\CashControl;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Domain\IAM\Models\User;

class RegisterSessionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected int $businessId;
    protected int $userId;
    protected int $locationId;
    protected int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userId = DB::table('users')->insertGetId([
            'first_name' => 'Cashier',
            'email' => 'cashier@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Store Front LLC', 
            'owner_id' => $this->userId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        DB::table('users')->where('id', $this->userId)->update(['business_id' => $this->businessId]);

        $this->locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Main Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Apple',
            'sku' => 'APL-1',
            'selling_price' => '150.0000',
            'purchase_price' => '100.0000',
            'current_stock' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->productId,
            'location_id' => $this->locationId,
            'qty_available' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_layers')->insert([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'original_qty' => '10.0000',
            'remaining_qty' => '10.0000',
            'unit_cost' => '100.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Manually trigger the observer since DB::table doesn't dispatch Eloquent events
        $businessModel = \App\Modules\Tenant\Models\Business::find($this->businessId);
        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created($businessModel);
    }

    public function test_checkout_fails_without_open_register_session()
    {
        $this->withoutMiddleware();
        $user = User::find($this->userId);
        
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'amount_paid' => 150,
            'tax_rate' => 0,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 1,
                    'price' => 150
                ]
            ]
        ], ['X-Device-Hash' => 'HASH_1']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'FPM Security: POS checkout blocked. Cash register drawer is closed, bound to another device, or currently suspending.']);
    }

    public function test_device_theft_rejection()
    {
        $this->withoutMiddleware();
        $user = User::find($this->userId);

        // Open on HASH_1
        $this->actingAs($user)->postJson('/api/v1/register/open', [
            'opening_balance' => 100
        ], ['X-Device-Hash' => 'HASH_1'])->assertStatus(201);

        // Attempt checkout on HASH_2
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'amount_paid' => 150,
            'tax_rate' => 0,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 1,
                    'price' => 150
                ]
            ]
        ], ['X-Device-Hash' => 'HASH_2']);

        $response->assertStatus(422);
    }

    public function test_device_lock_bypass_when_flag_disabled()
    {
        $this->withoutMiddleware();
        $user = User::find($this->userId);

        // Disable the feature flag for the tenant
        DB::table('businesses')->where('id', $this->businessId)->update([
            'settings' => json_encode(['pos_enforce_device_lock' => false])
        ]);

        // Open on HASH_1
        $this->actingAs($user)->postJson('/api/v1/register/open', [
            'opening_balance' => 100
        ], ['X-Device-Hash' => 'HASH_1'])->assertStatus(201);

        // Attempt checkout on HASH_2
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'amount_paid' => 150,
            'tax_rate' => 0,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 1,
                    'price' => 150
                ]
            ]
        ], ['X-Device-Hash' => 'HASH_2']);

        $response->assertStatus(201);
    }

    public function test_reconciliation_integrity_with_discrepancy_and_frozen_calculation()
    {
        $this->withoutMiddleware();
        $user = User::find($this->userId);
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH_DISCREPANCY);
        \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH);

        // 1. Open Register with $100
        $response = $this->actingAs($user)->postJson('/api/v1/register/open', [
            'opening_balance' => 100
        ], ['X-Device-Hash' => 'HASH_1']);
        $response->assertStatus(201);
        $registerId = $response->json('register_id');

        // 2. Process POS cash sale of $150
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'amount_paid' => 150,
            'tax_rate' => 0,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 1,
                    'price' => 150
                ]
            ]
        ], ['X-Device-Hash' => 'HASH_1']);
        $response->assertStatus(201);

        // 3. Suspend shift (Expected $250)
        // Add api route for suspend? Wait, I didn't add it to routes yet! Let me just use close since it also auto-calculates if not suspending.
        // Actually I should just call close. Wait, Test Case 2 explicitly asks for "The Frozen Account Calculation: Verify that transitioning the register to a closing phase blocks subsequent incoming sales logs from corrupting the static expected cash matrix."
        // Let's call /api/v1/register/suspend directly via HTTP if I add it to routes. Wait, I will add it to routes right after this.
        $this->actingAs($user)->postJson('/api/v1/register/suspend', [], ['X-Device-Hash' => 'HASH_1'])->assertStatus(200);

        // Verify checkout is blocked while suspending
        $response = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'amount_paid' => 10,
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1, 'price' => 10]
            ]
        ], ['X-Device-Hash' => 'HASH_1']);
        $response->assertStatus(422);

        // 4. Submit shift closing request with $240 (Expected $250 -> $10 Shortage)
        $response = $this->actingAs($user)->postJson('/api/v1/register/close', [
            'closing_balance_counted' => 240
        ], ['X-Device-Hash' => 'HASH_1']);
        
        $response->assertStatus(200);
        $response->assertJson([
            'closing_balance_expected' => 250,
            'closing_balance_counted' => 240,
            'discrepancy_amount' => -10
        ]);

        // 5. Assert Database Record
        $register = DB::table('cash_registers')->where('id', $registerId)->first();
        $this->assertEquals('-10.0000', $register->discrepancy_amount);

        // 6. Verify General Ledger hook executed a $10 Debit to CASH_DISCREPANCY
        $varianceAccount = \App\Modules\Finance\Services\TenantAccountResolver::resolve($this->businessId, \App\Modules\Finance\Services\TenantAccountResolver::CASH_DISCREPANCY);
        
        $journalEntry = \App\Models\JournalEntry::where('reference_id', $registerId)->where('reference_type', 'register_closure')->first();
        $this->assertNotNull($journalEntry);

        $varianceDebit = \App\Models\JournalLine::where('journal_entry_id', $journalEntry->id)
            ->where('chart_of_account_id', $varianceAccount)
            ->where('type', 'debit')
            ->first();
            
        $this->assertNotNull($varianceDebit);
        $this->assertEquals('10.0000', $varianceDebit->amount);
    }
}
