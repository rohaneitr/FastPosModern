<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Generates a Trial Balance by aggregating raw journal lines.
     */
    public function generateTrialBalance(int $businessId, string $startDate, string $endDate): array
    {
        // Leverage SQL aggregation to rapidly calculate sums rather than array mapping in PHP
        $query = DB::table('finance_journal_lines as jl')
            ->join('finance_journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('finance_accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.business_id', $businessId)
            ->whereBetween('je.entry_date', [$startDate, $endDate])
            ->select(
                'a.code',
                'a.name',
                'a.type',
                DB::raw("SUM(CASE WHEN jl.type = 'debit' THEN jl.amount ELSE 0 END) as total_debits"),
                DB::raw("SUM(CASE WHEN jl.type = 'credit' THEN jl.amount ELSE 0 END) as total_credits")
            )
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->get();

        $report = [];
        $totalDebit = '0.0000';
        $totalCredit = '0.0000';

        foreach ($query as $row) {
            $debit = (string)$row->total_debits;
            $credit = (string)$row->total_credits;
            
            // Calculate natural balance based on account type
            // Assets & Expenses naturally carry Debit balances.
            // Liabilities, Equity & Revenue naturally carry Credit balances.
            $balance = bcsub($debit, $credit, 4);
            $naturalBalance = 0;

            if (in_array(strtolower($row->type), ['asset', 'expense'])) {
                $naturalBalance = $balance; // positive means debit balance
            } else {
                $naturalBalance = bcsub($credit, $debit, 4); // positive means credit balance
            }

            $report['accounts'][] = [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'total_debits' => $debit,
                'total_credits' => $credit,
                'natural_balance' => $naturalBalance,
            ];

            $totalDebit = bcadd($totalDebit, $debit, 4);
            $totalCredit = bcadd($totalCredit, $credit, 4);
        }

        $report['summary'] = [
            'total_debits' => $totalDebit,
            'total_credits' => $totalCredit,
            'is_balanced' => bccomp($totalDebit, $totalCredit, 4) === 0,
        ];

        return $report;
    }

    /**
     * Generates a Profit & Loss (Income) Statement.
     * P&L only includes Revenue and Expense accounts.
     */
    public function generateProfitAndLoss(int $businessId, string $startDate, string $endDate): array
    {
        $trialBalance = $this->generateTrialBalance($businessId, $startDate, $endDate);
        
        $revenues = [];
        $expenses = [];
        
        $totalRevenue = '0.0000';
        $totalExpense = '0.0000';

        foreach ($trialBalance['accounts'] as $account) {
            $type = strtolower($account['type']);
            if ($type === 'revenue') {
                $revenues[] = $account;
                $totalRevenue = bcadd($totalRevenue, (string)$account['natural_balance'], 4);
            } elseif ($type === 'expense') {
                $expenses[] = $account;
                $totalExpense = bcadd($totalExpense, (string)$account['natural_balance'], 4);
            }
        }

        $netIncome = bcsub($totalRevenue, $totalExpense, 4);

        return [
            'revenues' => $revenues,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenses,
            'total_expense' => $totalExpense,
            'net_income' => $netIncome,
            'is_profitable' => bccomp($netIncome, '0.0000', 4) >= 0,
        ];
    }
}
