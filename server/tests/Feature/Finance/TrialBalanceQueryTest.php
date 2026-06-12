<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Finance\Queries\GetTenantTrialBalanceAction;
use App\Modules\Finance\Services\TenantAccountResolver;
use App\Modules\Sales\Services\FinancialCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class TrialBalanceQueryTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $userId;

    protected function setUp(): void
    {
        parent::setUp();
        
        TenantAccountResolver::clearCache();

        $this->userId = DB::table('users')->insertGetId([
            'first_name' => 'Trial Balance Admin',
            'email' => 'admin_tb@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Trial Balance Tenant', 
            'owner_id' => $this->userId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $this->userId)->update(['business_id' => $this->businessId]);

        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created(Business::find($this->businessId));
    }

    public function test_trial_balance_maintains_perfect_equilibrium_after_multiple_transactions()
    {
        $this->withoutMiddleware();
        $this->actingAs(User::find($this->userId));

        // 1. Setup Data for Transactions
        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Main Store',
        ]);

        $supplierId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'supplier',
            'name' => 'Supplier Co',
        ]);

        $customerId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'name' => 'Customer Co',
        ]);

        $unitId = DB::table('units')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Pieces',
            'short_name' => 'pc',
            'created_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Product A',
            'sku' => 'PROD-A',
            'unit_id' => $unitId,
            'purchase_price' => 100,
            'selling_price' => 200,
        ]);

        // Give stock manually for the sale so we don't hit validation errors
        DB::table('product_stocks')->insert([
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_available' => 100,
        ]);

        // 2. Perform Purchase ($500 Total, $200 Paid)
        $purchaseResponse = $this->postJson('/api/v1/purchases', [
            'contact_id' => $supplierId,
            'purchase_date' => now()->format('Y-m-d'),
            'status' => 'received',
            'tax_rate' => 0,
            'amount_paid' => 200.00,
            'payment_method' => 'cash',
            'lines' => [
                [
                    'product_id' => $productId,
                    'quantity' => 5,
                    'purchase_price' => 100.00,
                ]
            ]
        ]);
        $purchaseResponse->assertStatus(201);

        // 3. Perform Sale ($200 Subtotal, $10 discount, 10% tax = $209 Total. $209 Paid)
        $saleResponse = $this->postJson('/api/v1/checkout', [
            'location_id' => $locationId,
            'contact_id' => $customerId,
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => 200.00,
                ]
            ],
            'tax_rate' => 10,
            'discount_type' => 'fixed',
            'discount_amount' => 10.00,
            'amount_paid' => 209.00,
            'payment_method' => 'cash',
        ]);
        $saleResponse->assertStatus(201);

        // 4. Query Trial Balance
        $action = new GetTenantTrialBalanceAction();
        $trialBalance = $action->execute($this->businessId);

        $this->assertNotEmpty($trialBalance);

        $aggregateDebits = '0.0000';
        $aggregateCredits = '0.0000';

        foreach ($trialBalance as $account) {
            // Verify scale logic is returning exactly 4 decimal places via strings
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $account['total_debits']);
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $account['total_credits']);
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $account['net_balance']);

            $aggregateDebits = FinancialCalculator::add($aggregateDebits, $account['total_debits']);
            $aggregateCredits = FinancialCalculator::add($aggregateCredits, $account['total_credits']);
        }

        // 5. Grand Architectural Assertion
        $finalDebits = FinancialCalculator::toDbString($aggregateDebits);
        $finalCredits = FinancialCalculator::toDbString($aggregateCredits);

        // Total Debits MUST perfectly equal Total Credits
        $this->assertEquals($finalDebits, $finalCredits);

        // Calculate System Delta
        $systemDelta = FinancialCalculator::subtract($finalDebits, $finalCredits);
        $this->assertEquals('0.0000', FinancialCalculator::toDbString($systemDelta));
    }
}
