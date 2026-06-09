<?php

namespace App\Domain\Analytics\Services;

use App\Modules\Tenant\Models\Business;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConsolidatedAnalyticsEngine
{
    /**
     * Compute and cache the global analytics dashboard payload.
     */
    public function getDashboardPayload(int $businessId): array
    {
        $cacheKey = "analytics_overview_{$businessId}_rolling";
        
        return Cache::tags(['analytics', "business:{$businessId}"])->remember($cacheKey, 3600, function () use ($businessId) {
            $endDate = Carbon::now();
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();

            // 1. Total Metrics (All Time or YTD)
            // Using DB::raw string casting for precision decimal conversion.
            $metrics = DB::table('transactions')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->select(
                    DB::raw("SUM(CAST(total_amount AS DECIMAL(22,4)) * CAST(COALESCE(exchange_rate_used, 1.0000) AS DECIMAL(22,4))) as total_revenue"),
                    DB::raw("SUM(CAST(tax_amount AS DECIMAL(22,4)) * CAST(COALESCE(exchange_rate_used, 1.0000) AS DECIMAL(22,4))) as total_tax")
                )
                ->first();

            // COGS (Cost of Goods Sold) computed from Journal Lines mapped to Account 5000
            $cogsQuery = DB::table('journal_lines')
                ->where('business_id', $businessId)
                ->where('chart_of_account_code', '5000') // COGS
                ->whereNull('deleted_at')
                ->select(
                    DB::raw("SUM(CAST(debit_amount AS DECIMAL(22,4)) - CAST(credit_amount AS DECIMAL(22,4))) as cogs") // COGS is an expense, so normal balance is debit
                )
                ->first();

            $totalRevenue = $metrics->total_revenue ?? '0.0000';
            $cogs = $cogsQuery->cogs ?? '0.0000';
            $grossProfit = bcsub($totalRevenue, $cogs, 4);
            $netProfit = $grossProfit; // Simplified Net Profit for now, subtracts other expenses in full system

            // 2. Rolling 12-Month Chart Series
            // We group by year and month. Since SQLite lacks DATE_FORMAT, we use strftime
            // For MySQL it would be DATE_FORMAT(created_at, '%Y-%m')
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $dateExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

            $monthlySales = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('created_at', '>=', $startDate)
                ->whereNull('deleted_at')
                ->select(
                    DB::raw("{$dateExpr} as month_key"),
                    DB::raw("SUM(CAST(total_amount AS DECIMAL(22,4)) * CAST(COALESCE(exchange_rate_used, 1.0000) AS DECIMAL(22,4))) as revenue")
                )
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get();
            
            // Re-map to proper timeline
            $chartData = [];
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $monthKey = $currentDate->format('Y-m');
                $monthDisplay = $currentDate->format('M');
                
                $salesRow = $monthlySales->firstWhere('month_key', $monthKey);
                $revenue = $salesRow ? (float)$salesRow->revenue : 0;
                
                // For demonstration, COGS is simulated as 60% of revenue in chart if not explicitly queried per month
                $cogsVal = $revenue * 0.6;
                $profitVal = $revenue - $cogsVal;

                $chartData[] = [
                    'month' => $monthDisplay,
                    'revenue' => $revenue,
                    'cogs' => $cogsVal,
                    'profit' => $profitVal
                ];

                $currentDate->addMonth();
            }

            // 3. Top Products
            $topProducts = DB::table('transaction_items')
                ->join('products', 'transaction_items.product_id', '=', 'products.id')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->where('transactions.business_id', $businessId)
                ->select(
                    'products.name',
                    DB::raw("SUM(transaction_items.quantity) as units_sold"),
                    DB::raw("SUM(CAST(transaction_items.subtotal AS DECIMAL(22,4)) * CAST(COALESCE(transactions.exchange_rate_used, 1.0000) AS DECIMAL(22,4))) as revenue")
                )
                ->groupBy('products.name', 'products.id')
                ->orderByDesc('units_sold')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $item->revenue = number_format((float)$item->revenue, 4, '.', '');
                    return $item;
                });

            return [
                'metrics' => [
                    'total_revenue' => number_format((float)$totalRevenue, 4, '.', ''),
                    'cogs' => number_format((float)$cogs, 4, '.', ''),
                    'gross_profit' => number_format((float)$grossProfit, 4, '.', ''),
                    'net_profit' => number_format((float)$netProfit, 4, '.', ''),
                ],
                'charts' => [
                    'rolling_12_months' => $chartData
                ],
                'top_products' => $topProducts
            ];
        });
    }
}
