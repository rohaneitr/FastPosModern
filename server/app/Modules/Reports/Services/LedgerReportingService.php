<?php

namespace App\Modules\Reports\Services;

use Illuminate\Support\Facades\DB;

class LedgerReportingService
{
    /**
     * Verifies the fundamental accounting equation (Assets = Liabilities + Equity)
     * by ensuring Total Debits = Total Credits across all journal lines for a given period.
     */
    public function verifyTrialBalance(int $businessId, string $startDate, string $endDate): bool
    {
        $totals = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.business_id', $businessId)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('journal_lines.deleted_at')
            ->select(
                DB::raw("SUM(CASE WHEN journal_lines.type = 'debit' THEN journal_lines.amount ELSE 0 END) as total_debits"),
                DB::raw("SUM(CASE WHEN journal_lines.type = 'credit' THEN journal_lines.amount ELSE 0 END) as total_credits")
            )->first();

        // If they don't exactly match (accounting for decimal float precision up to 2 places)
        return round((float)$totals->total_debits, 2) === round((float)$totals->total_credits, 2);
    }

    /**
     * Generate Profit & Loss Report based strictly on the Ledger.
     */
    public function getProfitAndLoss(int $businessId, string $startDate, string $endDate): array
    {
        if (!$this->verifyTrialBalance($businessId, $startDate, $endDate)) {
            throw new \Exception('TrialBalanceMismatchException: The ledger does not balance for this period.');
        }

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.business_id', $businessId)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('journal_lines.deleted_at')
            ->whereIn('chart_of_accounts.type', ['Revenue', 'Expense', 'COGS'])
            ->select(
                'chart_of_accounts.type as account_type',
                'chart_of_accounts.name as account_name',
                DB::raw("SUM(CASE WHEN journal_lines.type = 'credit' THEN journal_lines.amount ELSE 0 END) as total_credits"),
                DB::raw("SUM(CASE WHEN journal_lines.type = 'debit' THEN journal_lines.amount ELSE 0 END) as total_debits")
            )
            ->groupBy('chart_of_accounts.type', 'chart_of_accounts.name')
            ->get();

        $revenue = [];
        $cogs = [];
        $expenses = [];
        
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalExpenses = 0;

        foreach ($lines as $line) {
            // Revenue normal balance is Credit
            if ($line->account_type === 'Revenue') {
                $balance = $line->total_credits - $line->total_debits;
                $revenue[] = ['name' => $line->account_name, 'balance' => $balance];
                $totalRevenue += $balance;
            }
            // COGS normal balance is Debit
            elseif ($line->account_type === 'COGS') {
                $balance = $line->total_debits - $line->total_credits;
                $cogs[] = ['name' => $line->account_name, 'balance' => $balance];
                $totalCogs += $balance;
            }
            // Expense normal balance is Debit
            elseif ($line->account_type === 'Expense') {
                $balance = $line->total_debits - $line->total_credits;
                $expenses[] = ['name' => $line->account_name, 'balance' => $balance];
                $totalExpenses += $balance;
            }
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $netIncome = $grossProfit - $totalExpenses;

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cogs' => $totalCogs,
                'gross_profit' => $grossProfit,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
            ]
        ];
    }
}
