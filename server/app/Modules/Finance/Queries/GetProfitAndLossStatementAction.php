<?php

namespace App\Modules\Finance\Queries;

use App\Modules\Sales\Services\FinancialCalculator;

class GetProfitAndLossStatementAction
{
    /**
     * Calculates the exact Profit & Loss (Income Statement) for a Tenant
     * utilizing the mathematically secure Trial Balance Query.
     *
     * @param int $businessId
     * @return array
     */
    public function execute(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $trialBalanceAction = new GetTenantTrialBalanceAction();
        $trialBalance = $trialBalanceAction->execute($businessId, $startDate, $endDate);

        $totalRevenue = '0.0000';
        $totalCogs = '0.0000';
        $totalExpenses = '0.0000';

        $revenueAccounts = [];
        $cogsAccounts = [];
        $expenseAccounts = [];

        foreach ($trialBalance as $account) {
            $balance = $account['net_balance'];
            $type = $account['type'];
            $code = $account['code'];

            if ($type === 'revenue') {
                $totalRevenue = FinancialCalculator::add($totalRevenue, $balance);
                $revenueAccounts[] = [
                    'account_name' => $account['name'],
                    'code' => $code,
                    'balance' => $balance
                ];
            } elseif ($code === '5000' || strtolower($account['name']) === 'cost of goods sold') {
                // Cost of Goods Sold is handled uniquely for Gross Profit
                $totalCogs = FinancialCalculator::add($totalCogs, $balance);
                $cogsAccounts[] = [
                    'account_name' => $account['name'],
                    'code' => $code,
                    'balance' => $balance
                ];
            } elseif ($type === 'expense') {
                // General Operating Expenses (Discounts, Utilities, Payroll)
                $totalExpenses = FinancialCalculator::add($totalExpenses, $balance);
                $expenseAccounts[] = [
                    'account_name' => $account['name'],
                    'code' => $code,
                    'balance' => $balance
                ];
            }
        }

        // Formulaic Calculations
        $grossProfit = FinancialCalculator::subtract($totalRevenue, $totalCogs);
        $netProfit = FinancialCalculator::subtract($grossProfit, $totalExpenses);

        return [
            'totals' => [
                'revenue' => FinancialCalculator::toDbString($totalRevenue),
                'cogs' => FinancialCalculator::toDbString($totalCogs),
                'gross_profit' => FinancialCalculator::toDbString($grossProfit),
                'operating_expenses' => FinancialCalculator::toDbString($totalExpenses),
                'net_profit' => FinancialCalculator::toDbString($netProfit),
            ],
            'breakdown' => [
                'revenue_accounts' => $revenueAccounts,
                'cogs_accounts' => $cogsAccounts,
                'expense_accounts' => $expenseAccounts,
            ]
        ];
    }
}
