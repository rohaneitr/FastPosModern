<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Finance\Queries\GetBalanceSheetAction;
use App\Modules\Finance\Services\TenantAccountResolver;
use App\Modules\Sales\Services\FinancialCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class BalanceSheetQueryTest extends TestCase
{
    use RefreshDatabase;

    protected $businessId;
    protected $userId;

    protected function setUp(): void
    {
        parent::setUp();
        
        TenantAccountResolver::clearCache();

        $this->userId = DB::table('users')->insertGetId([
            'first_name' => 'Balance Sheet Admin',
            'email' => 'admin_bs@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Balance Sheet Tenant', 
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

    public function test_balance_sheet_holds_absolute_equilibrium_across_operational_cycle()
    {
        $ledger = app(\App\Modules\Finance\Services\DoubleEntryEngine::class);

        $salesAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::SALES);
        $arAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::AR);
        $cogsAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::COGS);
        $inventoryAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::INVENTORY);
        $apAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::AP);
        $cashAccountId = TenantAccountResolver::resolve($this->businessId, TenantAccountResolver::CASH);

        $opExAccountId = DB::table('chart_of_accounts')->insertGetId([
            'business_id' => $this->businessId,
            'code' => '6000',
            'name' => 'Operating Expense',
            'type' => 'expense'
        ]);

        // Scenario 1: Initial Owner Capital Injection (To give us starting cash)
        $equityAccountId = DB::table('chart_of_accounts')->where('business_id', $this->businessId)->where('code', '3000')->first()->id;
        
        $ledger->recordEntry(
            $this->businessId,
            'CAP-001',
            now()->format('Y-m-d'),
            'Owner Capital Injection',
            [['chart_of_account_id' => $cashAccountId, 'amount' => '5000.0000']],
            [['chart_of_account_id' => $equityAccountId, 'amount' => '5000.0000']],
            1,
            'capital',
            $this->userId
        );

        // Scenario 2: Purchase Inventory for $1000. Pay $400 Cash, $600 Due (AP).
        $ledger->recordEntry(
            $this->businessId,
            'PO-001',
            now()->format('Y-m-d'),
            'Inventory Purchase',
            [['chart_of_account_id' => $inventoryAccountId, 'amount' => '1000.0000']],
            [
                ['chart_of_account_id' => $cashAccountId, 'amount' => '400.0000'],
                ['chart_of_account_id' => $apAccountId, 'amount' => '600.0000']
            ],
            2,
            'purchase',
            $this->userId
        );

        // Scenario 3: Sell entire inventory for $3000. Received $1500 Cash, $1500 Due (AR).
        $ledger->recordEntry(
            $this->businessId,
            'INV-001',
            now()->format('Y-m-d'),
            'Sales Revenue',
            [
                ['chart_of_account_id' => $cashAccountId, 'amount' => '1500.0000'],
                ['chart_of_account_id' => $arAccountId, 'amount' => '1500.0000']
            ],
            [['chart_of_account_id' => $salesAccountId, 'amount' => '3000.0000']],
            3,
            'sale',
            $this->userId
        );

        // Scenario 4: Record COGS for the sold inventory ($1000)
        $ledger->recordEntry(
            $this->businessId,
            'COGS-001',
            now()->format('Y-m-d'),
            'Cost of Goods Sold Recognition',
            [['chart_of_account_id' => $cogsAccountId, 'amount' => '1000.0000']],
            [['chart_of_account_id' => $inventoryAccountId, 'amount' => '1000.0000']],
            4,
            'cogs',
            $this->userId
        );

        // Scenario 5: Pay $200 in operating overhead (Cash)
        $ledger->recordEntry(
            $this->businessId,
            'EXP-001',
            now()->format('Y-m-d'),
            'Overhead Payment',
            [['chart_of_account_id' => $opExAccountId, 'amount' => '200.0000']],
            [['chart_of_account_id' => $cashAccountId, 'amount' => '200.0000']],
            5,
            'expense',
            $this->userId
        );

        // --- Execute Balance Sheet Generation ---
        $action = new GetBalanceSheetAction();
        $balanceSheet = $action->execute($this->businessId);
        $totals = $balanceSheet['totals'];

        /**
         * Mathematical Check:
         * Assets:
         * - Cash: 5000 (Capital) - 400 (Purchase) + 1500 (Sale) - 200 (Overhead) = 5900.0000
         * - AR: 1500.0000
         * - Inventory: 1000 (Purchase) - 1000 (COGS) = 0.0000
         * - Total Assets: 5900 + 1500 + 0 = 7400.0000
         * 
         * Liabilities:
         * - AP: 600.0000
         * 
         * Equity:
         * - Capital (Core): 5000.0000
         * - Net Profit (Retained Earnings): 3000 (Rev) - 1000 (COGS) - 200 (Exp) = 1800.0000
         * - Total Equity: 5000 + 1800 = 6800.0000
         * 
         * Total Liabilities & Equity: 600 + 6800 = 7400.0000
         */

        $this->assertEquals('7400.0000', $totals['assets']);
        $this->assertEquals('600.0000', $totals['liabilities']);
        $this->assertEquals('5000.0000', $totals['core_equity']);
        $this->assertEquals('1800.0000', $totals['retained_earnings']);
        $this->assertEquals('6800.0000', $totals['total_equity']);
        $this->assertEquals('7400.0000', $totals['liabilities_and_equity']);

        // Assert Absolute Equilibrium Delta
        $delta = FinancialCalculator::subtract($totals['assets'], $totals['liabilities_and_equity']);
        $this->assertEquals('0.0000', FinancialCalculator::toDbString($delta));
    }
}
