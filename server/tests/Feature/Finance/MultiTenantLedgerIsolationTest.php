<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\ChartOfAccount;
use App\Modules\Finance\Services\TenantAccountResolver;

class MultiTenantLedgerIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear resolver cache to prevent cross-test contamination
        TenantAccountResolver::clearCache();
    }

    public function test_new_business_automatically_seeds_chart_of_accounts()
    {
        $userAId = DB::table('users')->insertGetId(['first_name' => 'A', 'password' => 'A', 'created_at' => now(), 'updated_at' => now()]);
        $userBId = DB::table('users')->insertGetId(['first_name' => 'B', 'password' => 'B', 'created_at' => now(), 'updated_at' => now()]);

        $businessA = Business::create(['name' => 'Tenant A', 'owner_id' => $userAId]);
        $businessB = Business::create(['name' => 'Tenant B', 'owner_id' => $userBId]);

        $accountsA = ChartOfAccount::where('business_id', $businessA->id)->count();
        $accountsB = ChartOfAccount::where('business_id', $businessB->id)->count();

        // 10 accounts seeded by observer
        $this->assertEquals(10, $accountsA);
        $this->assertEquals(10, $accountsB);

        // Ensure completely different DB IDs are resolved
        $cashIdA = TenantAccountResolver::resolve($businessA->id, TenantAccountResolver::CASH);
        $cashIdB = TenantAccountResolver::resolve($businessB->id, TenantAccountResolver::CASH);

        $this->assertNotEquals($cashIdA, $cashIdB);
    }

    public function test_tenant_a_checkout_does_not_pollute_tenant_b_ledger()
    {
        // 1. Setup Tenant A
        $userAId = DB::table('users')->insertGetId([
            'first_name' => 'Admin A',
            'email' => 'adminA@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessAId = DB::table('businesses')->insertGetId([
            'name' => 'Tenant A', 
            'owner_id' => $userAId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $userAId)->update(['business_id' => $businessAId]);

        // Manually trigger the observer logic since we used insertGetId
        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created(Business::find($businessAId));

        // 2. Setup Tenant B
        $userBId = DB::table('users')->insertGetId([
            'first_name' => 'Admin B',
            'email' => 'adminB@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessBId = DB::table('businesses')->insertGetId([
            'name' => 'Tenant B', 
            'owner_id' => $userBId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $userBId)->update(['business_id' => $businessBId]);
        $observer->created(Business::find($businessBId));

        // Setup common entities for Tenant A
        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $businessAId,
            'name' => 'Store A',
        ]);

        $contactId = DB::table('contacts')->insertGetId([
            'business_id' => $businessAId,
            'type' => 'customer',
            'name' => 'John Doe A',
        ]);

        $unitId = DB::table('units')->insertGetId([
            'business_id' => $businessAId,
            'name' => 'Pieces',
            'short_name' => 'pc',
            'created_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $businessAId,
            'name' => 'Item A',
            'sku' => 'SKU-A',
            'unit_id' => $unitId,
            'purchase_price' => 50,
            'selling_price' => 100,
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_available' => 10,
        ]);

        // Execute Checkout as Tenant A
        $this->withoutMiddleware();
        $this->actingAs(User::find($userAId));

        $payload = [
            'location_id' => $locationId,
            'contact_id' => $contactId,
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
            'tax_rate' => 0,
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'amount_paid' => 100.00,
            'payment_method' => 'cash',
        ];

        $response = $this->postJson('/api/v1/checkout', $payload);
        $response->assertStatus(201);

        $transactionId = $response->json('transaction_id');

        // Verification
        // 1. Check Tenant A's Journal Entry exists
        $journalA = DB::table('journal_entries')->where('business_id', $businessAId)->where('reference_id', $transactionId)->first();
        $this->assertNotNull($journalA);

        // 2. Check lines are linked to Tenant A's Chart of Accounts
        $linesA = DB::table('journal_lines')->where('journal_entry_id', $journalA->id)->get();
        $this->assertNotEmpty($linesA);

        $cashAccountIdA = TenantAccountResolver::resolve($businessAId, TenantAccountResolver::CASH);
        
        $hasCashDebit = false;
        foreach ($linesA as $line) {
            if ($line->chart_of_account_id === $cashAccountIdA) {
                $hasCashDebit = true;
            }
            
            // Assert that NO account ID in the lines belongs to Tenant B
            $account = ChartOfAccount::find($line->chart_of_account_id);
            $this->assertEquals($businessAId, $account->business_id);
            $this->assertNotEquals($businessBId, $account->business_id);
        }

        $this->assertTrue($hasCashDebit);

        // 3. Verify Tenant B's ledger is completely untouched (0 records)
        $journalsB = DB::table('journal_entries')->where('business_id', $businessBId)->count();
        $this->assertEquals(0, $journalsB);
    }
}
