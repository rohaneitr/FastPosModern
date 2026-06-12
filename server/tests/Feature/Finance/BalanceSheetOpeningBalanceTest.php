<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Finance\Services\TenantAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class BalanceSheetOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        
        TenantAccountResolver::clearCache();

        Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);

        $this->adminId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'admin_bs_test@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'BS Snapshot Tenant', 
            'owner_id' => $this->adminId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $this->adminId)->update(['business_id' => $this->businessId]);
        User::find($this->adminId)->assignRole('BusinessAdmin');

        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created(Business::find($this->businessId));
    }

    public function test_balance_sheet_strictly_ignores_start_date_to_preserve_opening_balances()
    {
        $this->actingAs(User::find($this->adminId));
        $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);

        $cashAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::CASH);
        $equityAccountId = DB::table('chart_of_accounts')->where('business_id', $this->businessId)->where('code', '3000')->first()->id;
        $salesAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::SALES);

        // Period 1 (May): Capital Injection ($10,000)
        $ledger->recordEntry(
            $this->businessId,
            'CAP-MAY',
            '2026-05-01',
            'Initial Capital',
            [['chart_of_account_id' => $cashAccountId, 'amount' => '10000.0000']],
            [['chart_of_account_id' => $equityAccountId, 'amount' => '10000.0000']],
            1,
            'capital',
            $this->adminId
        );

        // Period 2 (June): standard Sale ($500)
        $ledger->recordEntry(
            $this->businessId,
            'SALE-JUNE',
            '2026-06-15',
            'June Sales',
            [['chart_of_account_id' => $cashAccountId, 'amount' => '500.0000']],
            [['chart_of_account_id' => $salesAccountId, 'amount' => '500.0000']],
            2,
            'sale',
            $this->adminId
        );

        // --- The Attack Vector ---
        // A user maliciously/ignorantly attempts to request the Balance Sheet just for June
        $response = $this->getJson('/api/v1/accounting/balance-sheet?start_date=2026-06-01&end_date=2026-06-30');
        $response->assertStatus(200);

        // --- The Architectural Proof ---
        // The logic MUST have ignored `start_date`. It must return the cumulative total from inception.
        
        // Total Assets: $10,000 (May Cash) + $500 (June Cash)
        $response->assertJsonPath('data.totals.assets', '10500.0000');
        
        // Total Equity: $10,000 (May Capital) + $500 (Retained Earnings/June Sale)
        $response->assertJsonPath('data.totals.core_equity', '10000.0000');
        $response->assertJsonPath('data.totals.retained_earnings', '500.0000');
        $response->assertJsonPath('data.totals.total_equity', '10500.0000');
        
        // The ultimate equilibrium check
        $response->assertJsonPath('data.totals.liabilities_and_equity', '10500.0000');
    }
}
