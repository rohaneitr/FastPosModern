<?php

namespace App\Modules\Reporting\Services;

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
    public function getProfitAndLoss(int $businessId, string $startDate, string $endDate, string $accountingMethod = 'accrual'): array
    {
        if (!$this->verifyTrialBalance($businessId, $startDate, $endDate)) {
            throw new \Exception('TrialBalanceMismatchException: The ledger does not balance for this period.');
        }

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.business_id', $businessId)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->whereNull('journal_entries.deleted_at');

        // Apply Accounting Method Logic
        if ($accountingMethod === 'cash') {
            // In Cash-Basis, we only recognize revenue and expenses when cash changes hands.
            // This means the journal entry MUST contain a line with a Cash account.
            $lines->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('journal_lines as jl2')
                      ->join('chart_of_accounts as coa2', 'jl2.chart_of_account_id', '=', 'coa2.id')
                      ->whereRaw('jl2.journal_entry_id = journal_entries.id')
                      ->where('coa2.name', 'like', '%Cash%')
                      ->orWhere('coa2.name', 'like', '%Bank%');
            });
        } elseif ($accountingMethod === 'accrual') {
            // In Accrual-Basis, we must account for Deferred Revenue.
            // Example: If a 1-year upfront payment was received, revenue is recognized monthly.
            // (Assumes a scheduled job moves Deferred Revenue -> Revenue). We just look at Revenue accounts.
            // No extra filtering needed for standard accrual, it natively pulls the Revenue/Expense lines.
        }

        $lines = $lines->whereIn('chart_of_accounts.type', ['revenue', 'expense', 'liability'])
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
            if (strtolower($line->account_type) === 'revenue') {
                $balance = $line->total_credits - $line->total_debits;
                $revenue[] = ['name' => $line->account_name, 'balance' => $balance];
                $totalRevenue += $balance;
            }
            // Deferred Revenue (Liability) - adjust if Accrual basis
            elseif (strtolower($line->account_type) === 'liability' && stripos($line->account_name, 'deferred revenue') !== false) {
                // If we want to show it on P&L, usually it's on Balance Sheet, but for reporting clarity we might show unrecognized
                // For standard P&L, deferred revenue is not shown. We skip it, as the actual revenue is recognized into a Revenue account.
                continue;
            }
            // COGS normal balance is Debit
            elseif (strtolower($line->account_type) === 'expense' && strtolower($line->account_name) === 'cost of goods sold') {
                $balance = $line->total_debits - $line->total_credits;
                $cogs[] = ['name' => $line->account_name, 'balance' => $balance];
                $totalCogs += $balance;
            }
            // Expense normal balance is Debit
            elseif (strtolower($line->account_type) === 'expense') {
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

    /**
     * Export Profit & Loss to CSV format
     */
    public function exportProfitAndLossCsv(int $businessId, string $startDate, string $endDate): string
    {
        $pnl = $this->getProfitAndLoss($businessId, $startDate, $endDate);
        
        $csvData = [];
        $csvData[] = ['FastPOS Financial Report'];
        $csvData[] = ['Type', 'Profit & Loss Statement'];
        $csvData[] = ['Period', "$startDate to $endDate"];
        $csvData[] = [];
        
        $csvData[] = ['Category', 'Account', 'Balance'];
        
        foreach ($pnl['revenue'] as $acc) {
            $csvData[] = ['Revenue', $acc['name'], number_format((float)$acc['balance'], 2, '.', '')];
        }
        $csvData[] = ['Total Revenue', '', number_format((float)$pnl['summary']['total_revenue'], 2, '.', '')];
        $csvData[] = [];
        
        foreach ($pnl['cogs'] as $acc) {
            $csvData[] = ['Cost of Goods Sold', $acc['name'], number_format((float)$acc['balance'], 2, '.', '')];
        }
        $csvData[] = ['Total COGS', '', number_format((float)$pnl['summary']['total_cogs'], 2, '.', '')];
        $csvData[] = [];
        
        $csvData[] = ['Gross Profit', '', number_format((float)$pnl['summary']['gross_profit'], 2, '.', '')];
        $csvData[] = [];
        
        foreach ($pnl['expenses'] as $acc) {
            $csvData[] = ['Expense', $acc['name'], number_format((float)$acc['balance'], 2, '.', '')];
        }
        $csvData[] = ['Total Expenses', '', number_format((float)$pnl['summary']['total_expenses'], 2, '.', '')];
        $csvData[] = [];
        
        $csvData[] = ['Net Income', '', number_format((float)$pnl['summary']['net_income'], 2, '.', '')];

        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
