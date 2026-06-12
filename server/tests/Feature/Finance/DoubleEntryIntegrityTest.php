<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Models\Location;
use App\Models\Product;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Modules\Finance\Exceptions\AccountingImbalanceException;
use App\Modules\Finance\Services\DoubleEntryEngine;

class DoubleEntryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $userId;
    protected $locationId;
    protected $productId;
    protected $contactId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Test Business', 
            'owner_id' => $this->userId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $this->userId)->update([
            'business_id' => $this->businessId
        ]);

        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created(Business::find($this->businessId));

        $this->locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Main Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->contactId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'name' => 'John Doe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unitId = DB::table('units')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Pieces',
            'short_name' => 'pc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Expensive Item',
            'sku' => 'EXP-01',
            'unit_id' => $unitId,
            'purchase_price' => 50.00,
            'selling_price' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_stocks')->insert([
            'product_id' => $this->productId,
            'location_id' => $this->locationId,
            'qty_available' => 10,
        ]);
    }

    public function test_sale_checkout_creates_balanced_journal_entry()
    {
        $this->withoutMiddleware();
        
        $user = User::find($this->userId);
        $this->actingAs($user);

        // Subtotal = $100. Tax = $10. Discount = $5. Final = $105. Paid = $100. Due = $5.
        $payload = [
            'location_id' => $this->locationId,
            'contact_id' => $this->contactId,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
            'tax_rate' => 0.10, // 10%
            'discount_type' => 'fixed',
            'discount_amount' => 5.00,
            'amount_paid' => 100.00,
            'payment_method' => 'cash',
        ];

        $response = $this->postJson('/api/v1/checkout', $payload);
        $response->assertStatus(201);

        $transactionId = $response->json('transaction_id');

        // Verify Journal Entry exists
        $journal = DB::table('journal_entries')->where('reference_id', $transactionId)->where('reference_type', 'transaction')->first();
        $this->assertNotNull($journal);

        // Verify Lines
        $lines = DB::table('journal_lines')->where('journal_entry_id', $journal->id)->get();

        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($lines as $line) {
            if ($line->type === 'debit') {
                $totalDebits += $line->amount;
            } else {
                $totalCredits += $line->amount;
            }
        }

        // Expected Debits: 
        // Cash: 100.00
        // AR: 5.00
        // Discount: 5.00
        // Total Debits: 110.00

        // Expected Credits:
        // Revenue: 100.00
        // Tax: 10.00
        // Total Credits: 110.00

        // Now that strict COGS is implemented, the entry includes COGS ($50.00) and Inventory reduction ($50.00)
        // Original checkout debits (109.5) + COGS Debit (50.0) = 159.5
        $this->assertEquals(159.5000, $totalDebits);
        $this->assertEquals(159.5000, $totalCredits);
        $this->assertEquals($totalDebits, $totalCredits);
    }

    public function test_double_entry_engine_throws_exception_on_imbalance()
    {
        $engine = new DoubleEntryEngine();

        $this->expectException(AccountingImbalanceException::class);

        $engine->recordEntry(
            $this->businessId,
            'REF-001',
            now()->toDateString(),
            'Test Imbalance',
            [['chart_of_account_id' => 1, 'amount' => '100.0000']], // Debit 100
            [['chart_of_account_id' => 2, 'amount' => '99.0000']]   // Credit 99
        );
    }
}
