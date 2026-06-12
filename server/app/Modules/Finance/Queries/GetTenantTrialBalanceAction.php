<?php

namespace App\Modules\Finance\Queries;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Services\FinancialCalculator;

class GetTenantTrialBalanceAction
{
    /**
     * Executes an optimized DB-level aggregation to generate the Trial Balance.
     * Memory-safe execution for millions of rows.
     * 
     * @param int $businessId
     * @return array
     */
    public function execute(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('chart_of_accounts as coa')
            ->select([
                'coa.id as account_id',
                'coa.code',
                'coa.name',
                'coa.type',
                DB::raw("COALESCE(SUM(CASE WHEN jl.type = 'debit' THEN jl.amount ELSE 0 END), 0) as total_debits"),
                DB::raw("COALESCE(SUM(CASE WHEN jl.type = 'credit' THEN jl.amount ELSE 0 END), 0) as total_credits")
            ])
            ->join('journal_lines as jl', 'jl.chart_of_account_id', '=', 'coa.id')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('coa.business_id', $businessId)
            ->where('je.business_id', $businessId);

        if ($startDate) {
            $query->whereDate('je.date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('je.date', '<=', $endDate);
        }

        $rows = $query->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type')
            ->orderBy('coa.code', 'asc')
            ->get();

        $trialBalance = [];

        foreach ($rows as $row) {
            $debits = FinancialCalculator::toDbString($row->total_debits);
            $credits = FinancialCalculator::toDbString($row->total_credits);

            // Determine normal balance based on standard accounting rules
            $isDebitNormal = in_array($row->type, ['asset', 'expense']);
            
            if ($isDebitNormal) {
                $netBalance = FinancialCalculator::subtract($debits, $credits);
            } else {
                $netBalance = FinancialCalculator::subtract($credits, $debits);
            }

            $trialBalance[] = [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'total_debits' => $debits,
                'total_credits' => $credits,
                'net_balance' => FinancialCalculator::toDbString($netBalance),
            ];
        }

        return $trialBalance;
    }
}
