<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Modules\Finance\Services\TenantAccountResolver;

class PurchaseLedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $userId;
    protected $contactId;
    protected $productId;

    protected function setUp(): void
    {
        parent::setUp();
        
        TenantAccountResolver::clearCache();

        $this->userId = DB::table('users')->insertGetId([
            'first_name' => 'Admin Purchase',
            'email' => 'admin_purch@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Purchase Tenant', 
            'owner_id' => $this->userId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $this->userId)->update(['business_id' => $this->businessId]);

        // Trigger Observer for COA Seeding
        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created(Business::find($this->businessId));

        $this->contactId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'supplier',
            'name' => 'Global Supplier Inc',
        ]);

        $unitId = DB::table('units')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Pieces',
            'short_name' => 'pc',
            'created_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Wholesale Item',
            'sku' => 'WHOLE-1',
            'unit_id' => $unitId,
            'purchase_price' => 100, // We will buy 5 of these = $500
            'selling_price' => 200,
        ]);
    }

    public function test_purchase_creates_balanced_journal_entry()
    {
        $this->withoutMiddleware();
        $this->actingAs(User::find($this->userId));

        // Create a Supplier PO totaling $500.00. Pay $200.00 upfront, leaving $300.00 as due.
        $payload = [
            'contact_id' => $this->contactId,
            'purchase_date' => now()->format('Y-m-d'),
            'status' => 'received',
            'tax_rate' => 0,
            'amount_paid' => 200.00,
            'payment_method' => 'cash',
            'lines' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 5,
                    'purchase_price' => 100.00, // 5 * 100 = 500
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/purchases', $payload);
        if ($response->status() !== 201) {
            dump($response->json());
        }
        $response->assertStatus(201);

        $purchaseId = $response->json('data.id');

        // Verification
        $journal = JournalEntry::where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->first();

        $this->assertNotNull($journal, 'Journal entry for purchase should exist');

        $lines = JournalLine::where('journal_entry_id', $journal->id)->get();

        $inventoryAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::INVENTORY);
        $cashAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::CASH);
        $apAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::AP);

        $inventoryDebit = $lines->where('chart_of_account_id', $inventoryAccountId)->where('type', 'debit')->first();
        $cashCredit = $lines->where('chart_of_account_id', $cashAccountId)->where('type', 'credit')->first();
        $apCredit = $lines->where('chart_of_account_id', $apAccountId)->where('type', 'credit')->first();

        $this->assertNotNull($inventoryDebit, 'Missing Inventory Debit');
        $this->assertNotNull($cashCredit, 'Missing Cash Credit');
        $this->assertNotNull($apCredit, 'Missing AP Credit');

        $this->assertEquals('500.0000', $inventoryDebit->amount);
        $this->assertEquals('200.0000', $cashCredit->amount);
        $this->assertEquals('300.0000', $apCredit->amount);

        // Ensure balancing
        $totalDebits = $lines->where('type', 'debit')->sum('amount');
        $totalCredits = $lines->where('type', 'credit')->sum('amount');
        $this->assertEquals('500.0000', number_format($totalDebits, 4, '.', ''));
        $this->assertEquals('500.0000', number_format($totalCredits, 4, '.', ''));
    }
}
