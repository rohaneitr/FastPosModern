<?php

namespace App\Modules\Finance\Queries;

use App\Modules\Sales\Services\FinancialCalculator;
use App\Modules\Finance\Exceptions\BalanceSheetImbalanceException;

class GetBalanceSheetAction
{
    /**
     * Executes the generation of the Balance Sheet and mathematically enforces
     * the accounting equilibrium rule (Assets = Liabilities + Equity).
     *
     * @param int $businessId
     * @return array
     * @throws BalanceSheetImbalanceException
     */
    public function execute(int $businessId, ?string $asOfDate = null): array
    {
        $trialBalanceAction = new GetTenantTrialBalanceAction();
        // A Balance Sheet is a cumulative snapshot. Start date is explicitly NULL.
        $trialBalance = $trialBalanceAction->execute($businessId, null, $asOfDate);

        $totalAssets = '0.0000';
        $totalLiabilities = '0.0000';
        $coreEquity = '0.0000';

        $assetAccounts = [];
        $liabilityAccounts = [];
        $equityAccounts = [];

        foreach ($trialBalance as $account) {
            $balance = $account['net_balance'];
            $type = $account['type'];

            if ($type === 'asset') {
                $totalAssets = FinancialCalculator::add($totalAssets, $balance);
                $assetAccounts[] = [
                    'account_name' => $account['name'],
                    'code' => $account['code'],
                    'balance' => $balance
                ];
            } elseif ($type === 'liability') {
                $totalLiabilities = FinancialCalculator::add($totalLiabilities, $balance);
                $liabilityAccounts[] = [
                    'account_name' => $account['name'],
                    'code' => $account['code'],
                    'balance' => $balance
                ];
            } elseif ($type === 'equity') {
                $coreEquity = FinancialCalculator::add($coreEquity, $balance);
                $equityAccounts[] = [
                    'account_name' => $account['name'],
                    'code' => $account['code'],
                    'balance' => $balance
                ];
            }
        }

        // Dynamically fetch Lifetime Net Profit to act as Retained Earnings
        $pnlAction = new GetProfitAndLossStatementAction();
        // Retained Earnings represents all profit from inception up to the As-Of Date.
        $pnl = $pnlAction->execute($businessId, null, $asOfDate);
        $retainedEarnings = $pnl['totals']['net_profit'];

        // Aggregate Equity
        $totalEquity = FinancialCalculator::add($coreEquity, $retainedEarnings);
        $totalLiabilitiesAndEquity = FinancialCalculator::add($totalLiabilities, $totalEquity);

        // Enforce The Absolute Equilibrium Rule
        $systemDelta = FinancialCalculator::subtract($totalAssets, $totalLiabilitiesAndEquity);
        
        if (FinancialCalculator::toDbString($systemDelta) !== '0.0000') {
            throw new BalanceSheetImbalanceException(
                FinancialCalculator::toDbString($totalAssets), 
                FinancialCalculator::toDbString($totalLiabilitiesAndEquity)
            );
        }

        return [
            'totals' => [
                'assets' => FinancialCalculator::toDbString($totalAssets),
                'liabilities' => FinancialCalculator::toDbString($totalLiabilities),
                'core_equity' => FinancialCalculator::toDbString($coreEquity),
                'retained_earnings' => FinancialCalculator::toDbString($retainedEarnings),
                'total_equity' => FinancialCalculator::toDbString($totalEquity),
                'liabilities_and_equity' => FinancialCalculator::toDbString($totalLiabilitiesAndEquity),
            ],
            'breakdown' => [
                'asset_accounts' => $assetAccounts,
                'liability_accounts' => $liabilityAccounts,
                'equity_accounts' => $equityAccounts,
            ]
        ];
    }
}
