<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Finance\Services\TenantAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FinancialReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $adminId;
    protected $cashierId;

    protected function setUp(): void
    {
        parent::setUp();
        
        TenantAccountResolver::clearCache();

        Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);

        $this->adminId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'admin_api@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cashierId = DB::table('users')->insertGetId([
            'first_name' => 'Cashier',
            'email' => 'cashier_api@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'API Tenant', 
            'owner_id' => $this->adminId,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('users')->where('id', $this->adminId)->update(['business_id' => $this->businessId]);
        DB::table('users')->where('id', $this->cashierId)->update(['business_id' => $this->businessId]);

        User::find($this->adminId)->assignRole('BusinessAdmin');
        User::find($this->cashierId)->assignRole('Cashier');

        $observer = new \App\Modules\Tenant\Observers\BusinessObserver();
        $observer->created(Business::find($this->businessId));
    }

    public function test_cashier_is_forbidden_from_viewing_balance_sheet()
    {
        $this->actingAs(User::find($this->cashierId));

        $response = $this->getJson('/api/v1/accounting/balance-sheet');
        $response->assertStatus(403);
    }

    public function test_business_admin_can_filter_profit_and_loss_by_date_range()
    {
        $this->actingAs(User::find($this->adminId));
        $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);

        $salesAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::SALES);
        $cashAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::CASH);

        // Transaction 1: May 15th ($1000 Sale)
        $ledger->recordEntry(
            $this->businessId,
            'MAY-SALE',
            '2026-05-15',
            'May Sale',
            [['chart_of_account_id' => $cashAccountId, 'amount' => '1000.0000']],
            [['chart_of_account_id' => $salesAccountId, 'amount' => '1000.0000']],
            1,
            'sale',
            $this->adminId
        );

        // Transaction 2: June 5th ($5000 Sale)
        $ledger->recordEntry(
            $this->businessId,
            'JUNE-SALE',
            '2026-06-05',
            'June Sale',
            [['chart_of_account_id' => $cashAccountId, 'amount' => '5000.0000']],
            [['chart_of_account_id' => $salesAccountId, 'amount' => '5000.0000']],
            2,
            'sale',
            $this->adminId
        );

        // Request P&L for May only
        $response = $this->getJson('/api/v1/accounting/profit-and-loss?start_date=2026-05-01&end_date=2026-05-31');
        $response->assertStatus(200);

        // Only the $1000 sale from May should be aggregated
        $response->assertJsonPath('data.totals.revenue', '1000.0000');
        $response->assertJsonPath('data.totals.gross_profit', '1000.0000');
        $response->assertJsonPath('data.totals.net_profit', '1000.0000');

        // Request P&L for June only
        $responseJune = $this->getJson('/api/v1/accounting/profit-and-loss?start_date=2026-06-01&end_date=2026-06-30');
        $responseJune->assertStatus(200);

        // Only the $5000 sale from June should be aggregated
        $responseJune->assertJsonPath('data.totals.revenue', '5000.0000');
        $responseJune->assertJsonPath('data.totals.gross_profit', '5000.0000');
        $responseJune->assertJsonPath('data.totals.net_profit', '5000.0000');

        // Request P&L for entire period (May to June)
        $responseTotal = $this->getJson('/api/v1/accounting/profit-and-loss?start_date=2026-05-01&end_date=2026-06-30');
        $responseTotal->assertStatus(200);

        // Total should be $6000
        $responseTotal->assertJsonPath('data.totals.revenue', '6000.0000');
    }
}
