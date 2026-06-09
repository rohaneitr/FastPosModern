<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Finance\Queries\GetProfitAndLossStatementAction;
use App\Modules\Finance\Services\TenantAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ProfitAndLossStatementTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $userId;

    protected function setUp(): void
    {
        parent::setUp();
        
        TenantAccountResolver::clearCache();

        $this->userId = DB::table('users')->insertGetId([
            'first_name' => 'PNL Admin',
            'email' => 'admin_pnl@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'PNL Tenant', 
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

    public function test_profit_and_loss_engine_calculates_correct_hierarchical_totals()
    {
        // We will directly inject balanced journal entries simulating a complex operational month
        $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);

        $salesAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::SALES);
        $arAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::AR);
        $cogsAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::COGS);
        $inventoryAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::INVENTORY);
        $discountAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::DISCOUNT);
        $cashAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::CASH);

        // We also need an arbitrary Operating Expense account
        $opExAccountId = DB::table('chart_of_accounts')->insertGetId([
            'business_id' => $this->businessId,
            'code' => '6000',
            'name' => 'Utilities Expense',
            'type' => 'expense'
        ]);

        // Transaction 1: Revenue Recognition ($1000 Sale)
        $ledger->recordEntry(
            $this->businessId,
            'INV-001',
            now()->format('Y-m-d'),
            'Sales Invoice',
            [['chart_of_account_id' => $arAccountId, 'amount' => '1000.0000']],
            [['chart_of_account_id' => $salesAccountId, 'amount' => '1000.0000']],
            1,
            'sale',
            $this->userId
        );

        // Transaction 2: COGS Recognition ($400 Inventory Depletion)
        $ledger->recordEntry(
            $this->businessId,
            'COGS-001',
            now()->format('Y-m-d'),
            'Cost of Goods Sold',
            [['chart_of_account_id' => $cogsAccountId, 'amount' => '400.0000']],
            [['chart_of_account_id' => $inventoryAccountId, 'amount' => '400.0000']],
            1,
            'cogs',
            $this->userId
        );

        // Transaction 3: Overhead Expenses (Discount + Utilities Paid by Cash)
        // Total expense hit: $150
        $ledger->recordEntry(
            $this->businessId,
            'EXP-001',
            now()->format('Y-m-d'),
            'Monthly Overhead',
            [
                ['chart_of_account_id' => $discountAccountId, 'amount' => '50.0000'],
                ['chart_of_account_id' => $opExAccountId, 'amount' => '100.0000']
            ],
            [['chart_of_account_id' => $cashAccountId, 'amount' => '150.0000']],
            1,
            'expense',
            $this->userId
        );

        // Call PNL Engine
        $action = new GetProfitAndLossStatementAction();
        $pnl = $action->execute($this->businessId);

        $totals = $pnl['totals'];

        // Assert mathematically perfect hierarchy
        $this->assertEquals('1000.0000', $totals['revenue']);
        $this->assertEquals('400.0000', $totals['cogs']);
        
        // Gross Profit = 1000 - 400 = 600
        $this->assertEquals('600.0000', $totals['gross_profit']);
        
        // Operating Expenses = 50 (Discount) + 100 (Utilities) = 150
        $this->assertEquals('150.0000', $totals['operating_expenses']);
        
        // Net Profit = 600 - 150 = 450
        $this->assertEquals('450.0000', $totals['net_profit']);
    }

    public function test_pnl_handles_net_loss_scenario_safely()
    {
        $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);

        $cashAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::CASH);
        
        $opExAccountId = DB::table('chart_of_accounts')->insertGetId([
            'business_id' => $this->businessId,
            'code' => '6000',
            'name' => 'Massive Expense',
            'type' => 'expense'
        ]);

        // Zero Revenue, only $500 in Expenses
        $ledger->recordEntry(
            $this->businessId,
            'EXP-LOSS',
            now()->format('Y-m-d'),
            'Devastating Expense',
            [['chart_of_account_id' => $opExAccountId, 'amount' => '500.0000']],
            [['chart_of_account_id' => $cashAccountId, 'amount' => '500.0000']],
            1,
            'expense',
            $this->userId
        );

        $action = new GetProfitAndLossStatementAction();
        $pnl = $action->execute($this->businessId);

        $totals = $pnl['totals'];

        $this->assertEquals('0.0000', $totals['revenue']);
        $this->assertEquals('0.0000', $totals['cogs']);
        $this->assertEquals('0.0000', $totals['gross_profit']);
        $this->assertEquals('500.0000', $totals['operating_expenses']);
        $this->assertEquals('-500.0000', $totals['net_profit']); // Exact negative formatting
    }
}
