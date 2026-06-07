<?php

namespace App\Domain\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{

    /**
     * Dashboard KPIs — real-time metrics for the business dashboard.
     */
    public function dashboardKPIs(Request $request)
    {
        $businessId = $request->user()->business_id;
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $cacheKey = "dashboard_kpis_business_{$businessId}";
        
        $data = \Illuminate\Support\Facades\Cache::store('redis')->remember($cacheKey, 900, function () use ($today, $yesterday) {
            // Today's sales
            $todaySales = DB::table('transactions')
                ->tenant()->where('type', 'sell')->where('status', 'final')
                ->whereDate('transaction_date', $today)->sum('final_total');

            $yesterdaySales = DB::table('transactions')
                ->tenant()->where('type', 'sell')->where('status', 'final')
                ->whereDate('transaction_date', $yesterday)->sum('final_total');

            $salesChange = $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1) : 0;

            // Products sold today
            $productsSold = DB::table('transaction_lines')
                ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
                ->tenant('transactions')->where('transactions.type', 'sell')->where('transactions.status', 'final')
                ->whereDate('transactions.transaction_date', $today)->sum('transaction_lines.quantity');

            $productsSoldYesterday = DB::table('transaction_lines')
                ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
                ->tenant('transactions')->where('transactions.type', 'sell')->where('transactions.status', 'final')
                ->whereDate('transactions.transaction_date', $yesterday)->sum('transaction_lines.quantity');

            $productsSoldChange = $productsSoldYesterday > 0 ? round((($productsSold - $productsSoldYesterday) / $productsSoldYesterday) * 100, 1) : 0;

            // Total customers
            $totalCustomers = DB::table('contacts')
                ->tenant()->whereNull('deleted_at')
                ->whereIn('type', ['customer', 'both'])->count();

            $newCustomersThisWeek = DB::table('contacts')
                ->tenant()->whereNull('deleted_at')
                ->whereIn('type', ['customer', 'both'])
                ->where('created_at', '>=', Carbon::now()->subWeek())->count();

            // Low stock alerts (qty < 10)
            $lowStockCount = DB::table('product_stocks')
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->tenant('products')->whereNull('products.deleted_at')
                ->where('product_stocks.qty_available', '<', 10)->count();

            $lowStockItems = DB::table('product_stocks')
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->tenant('products')->whereNull('products.deleted_at')
                ->where('product_stocks.qty_available', '<', 10)
                ->select('products.name', 'products.sku', 'product_stocks.qty_available')
                ->orderBy('product_stocks.qty_available', 'asc')
                ->limit(10)
                ->get();

            // Top 5 selling products (last 30 days)
            $topProducts = DB::table('transaction_lines')
                ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_lines.product_id', '=', 'products.id')
                ->tenant('transactions')->where('transactions.type', 'sell')->where('transactions.status', 'final')
                ->where('transactions.transaction_date', '>=', Carbon::now()->subDays(30))
                ->select('products.name', DB::raw('SUM(transaction_lines.quantity) as qty_sold'), DB::raw('SUM(transaction_lines.quantity * transaction_lines.unit_price) as revenue'))
                ->groupBy('products.name')->orderByDesc('qty_sold')->limit(5)->get();

            // 7-day sales trend
            $salesTrend = DB::table('transactions')
                ->tenant()->where('type', 'sell')->where('status', 'final')
                ->where('transaction_date', '>=', Carbon::now()->subDays(7))
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(final_total) as total'), DB::raw('COUNT(id) as count'))
                ->groupBy('date')->orderBy('date')->get();

            // Recent 5 transactions
            $recentTransactions = DB::table('transactions')
                ->leftJoin('users', 'transactions.created_by', '=', 'users.id')
                ->tenant('transactions')->where('transactions.type', 'sell')
                ->select('transactions.invoice_no', 'transactions.transaction_date', 'transactions.final_total', 'transactions.status',
                    DB::raw("users.first_name || ' ' || users.last_name as cashier_name"))
                ->orderByDesc('transactions.transaction_date')->limit(5)->get();
                
            // Analytics Refactor: True Net Profit
            $totalCogs = DB::table('transaction_lines')
                ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_lines.product_id', '=', 'products.id')
                ->tenant('transactions')->where('transactions.type', 'sell')->where('transactions.status', 'final')
                ->sum(DB::raw('transaction_lines.quantity * products.purchase_price'));

            $totalRevenue = DB::table('transactions')
                ->tenant()->where('type', 'sell')->where('status', 'final')
                ->sum('final_total');
                
            $totalRefunds = DB::table('transactions')
                ->tenant()->where('type', 'sell_return')->where('status', 'final')
                ->sum('final_total');
                
            $totalExpenses = DB::table('expenses')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->sum('total_amount');
                
            $expensesThisMonth = DB::table('expenses')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->whereMonth('expense_date', Carbon::now()->month)
                ->whereYear('expense_date', Carbon::now()->year)
                ->sum('total_amount');
                
            $netProfit = ($totalRevenue - $totalRefunds) - $totalCogs - $totalExpenses;

            // Total Dues
            $totalDues = DB::table('transactions')
                ->tenant()
                ->where('type', 'sell')
                ->sum('amount_due');
                
            return [
                'today_sales' => round($todaySales, 2),
                'sales_change_pct' => $salesChange,
                'products_sold' => (int) $productsSold,
                'products_sold_change_pct' => $productsSoldChange,
                'total_customers' => $totalCustomers,
                'new_customers_this_week' => $newCustomersThisWeek,
                'low_stock_count' => $lowStockCount,
                'low_stock_items' => $lowStockItems,
                'top_products' => $topProducts,
                'sales_trend' => $salesTrend,
                'recent_transactions' => $recentTransactions,
                'net_profit' => round($netProfit, 2),
                'total_dues' => round($totalDues, 2),
                'total_expenses_this_month' => round($expensesThisMonth, 2),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get Profit & Loss Summary
     */
    public function profitLoss(Request $request)
    {
        $businessId = $request->user()->business_id;

        // Total Sales (excluding tax)
        $totalSales = DB::table('transactions')
            ->tenant()
            ->where('type', 'sell')
            ->where('status', 'final')
            ->sum('total_before_tax');

        // Total Sell Returns
        $totalSellReturns = DB::table('transactions')
            ->tenant()
            ->where('type', 'sell_return')
            ->where('status', 'final')
            ->sum('total_before_tax');

        // Net Sales
        $netSales = $totalSales - $totalSellReturns;

        // Total Purchases (excluding tax)
        $totalPurchases = DB::table('transactions')
            ->tenant()
            ->where('type', 'purchase')
            ->where('status', 'received')
            ->sum('total_before_tax');

        // Total Expenses
        $totalExpenses = DB::table('expenses')
            ->tenant()
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $netProfit = $netSales - $totalPurchases - $totalExpenses;

        return response()->json([
            'total_sales' => $totalSales,
            'total_sell_returns' => $totalSellReturns,
            'net_sales' => $netSales,
            'total_purchases' => $totalPurchases,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
        ]);
    }

    /**
     * Get Sales Report by Date Range
     */
    public function salesReport(Request $request)
    {
        $businessId = $request->user()->business_id;
        
        $startDate = $request->query('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->query('end_date', Carbon::now()->endOfDay());

        $sales = DB::table('transactions')
            ->tenant()
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('SUM(total_before_tax) as daily_total'),
                DB::raw('COUNT(id) as total_transactions')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($sales);
    }

    /**
     * Export Sales Report as CSV (Streaming)
     */
    public function exportSales(Request $request)
    {
        $businessId = $request->user()->business_id;
        
        $startDate = $request->query('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->query('end_date', Carbon::now()->endOfDay());

        $transactions = DB::table('transactions')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->leftJoin('users', 'transactions.created_by', '=', 'users.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sell')
            ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            ->select(
                'transactions.invoice_no',
                'transactions.transaction_date',
                'transactions.status',
                'transactions.payment_status',
                'transactions.final_total',
                'transactions.amount_due',
                'contacts.name as customer_name',
                DB::raw("COALESCE(users.first_name, '') || ' ' || COALESCE(users.last_name, '') as cashier")
            )
            ->orderBy('transactions.transaction_date', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions_export_' . time() . '.csv"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // Header Row
            fputcsv($file, [
                'Invoice No',
                'Date',
                'Customer',
                'Status',
                'Payment Status',
                'Total Amount',
                'Due Amount',
                'Cashier'
            ]);

            // Data Rows
            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->invoice_no,
                    Carbon::parse($tx->transaction_date)->toDateTimeString(),
                    $tx->customer_name ?? 'Walk-in',
                    strtoupper($tx->status),
                    strtoupper($tx->payment_status),
                    number_format($tx->final_total, 2, '.', ''),
                    number_format($tx->amount_due, 2, '.', ''),
                    trim($tx->cashier)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * End of Day (Z-Report)
     */
    public function endOfDayReport(Request $request)
    {
        $businessId = $request->user()->business_id;
        $today = Carbon::today();

        $transactions = DB::table('transactions')
            ->tenant()
            ->whereDate('transaction_date', $today)
            ->whereIn('type', ['sell', 'sell_return'])
            ->where('status', 'final')
            ->get();

        $totalCash = 0;
        $totalMobile = 0;
        $totalCard = 0;
        $totalSales = 0;
        $totalReturns = 0;

        $transactionIds = $transactions->pluck('id')->toArray();
        $payments = DB::table('transaction_payments')
            ->whereIn('transaction_id', $transactionIds)
            ->get();

        foreach ($transactions as $tx) {
            if ($tx->type === 'sell') {
                $totalSales += $tx->final_total;
            } elseif ($tx->type === 'sell_return') {
                $totalReturns += $tx->final_total;
            }
        }

        foreach ($payments as $payment) {
            $amount = $payment->amount;
            // Subtract amount if it's a payment for a sell_return (refund)
            $tx = $transactions->firstWhere('id', $payment->transaction_id);
            if ($tx && $tx->type === 'sell_return') {
                $amount = -$amount;
            }

            if ($payment->method === 'cash') {
                $totalCash += $amount;
            } elseif (in_array($payment->method, ['bkash', 'nagad', 'rocket', 'mobile', 'bank_transfer'])) {
                $totalMobile += $amount;
            } elseif (in_array($payment->method, ['card', 'sslcommerz'])) {
                $totalCard += $amount;
            }
        }

        $cashExpenses = DB::table('expenses')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->where('payment_method', 'cash')
            ->whereDate('expense_date', $today)
            ->sum('total_amount');

        $netCollected = $totalCash + $totalMobile + $totalCard - $cashExpenses;

        return response()->json([
            'date' => $today->toDateString(),
            'total_sales' => round($totalSales, 2),
            'total_returns' => round($totalReturns, 2),
            'net_sales' => round($totalSales - $totalReturns, 2),
            'cash_expenses' => round($cashExpenses, 2),
            'collected' => [
                'cash' => round($totalCash, 2),
                'mobile' => round($totalMobile, 2),
                'card' => round($totalCard, 2),
                'total' => round($netCollected, 2),
            ]
        ]);
    }

    /**
     * Monthly Payroll vs. Revenue comparative report
     */
    public function payrollVsRevenue(Request $request)
    {
        $businessId = $request->user()->business_id;
        $months = 6;

        $report = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonthsNoOverflow($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonthsNoOverflow($i)->endOfMonth();

            // Revenue for the month
            $revenue = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('type', 'sell')
                ->where('status', 'final')
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->sum('final_total');

            // Payroll (Expenses mapped to Payroll)
            $payroll = DB::table('expenses')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->where('expense_category_id', function($q) use ($businessId) {
                    $q->select('id')
                      ->from('expense_categories')
                      ->where('business_id', $businessId)
                      ->where('name', 'Salaries & Wages')
                      ->limit(1);
                })
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            // Fallback: If no explicit category, fallback to checking HR payroll records
            if ($payroll == 0) {
                $payroll = DB::table('payrolls')
                    ->where('business_id', $businessId)
                    ->where('status', 'paid')
                    ->where('month', $monthStart->month)
                    ->where('year', $monthStart->year)
                    ->sum('net_salary');
            }

            $report[] = [
                'month' => $monthStart->format('M Y'),
                'revenue' => round($revenue, 2),
                'payroll' => round($payroll, 2),
                'ratio' => $revenue > 0 ? round(($payroll / $revenue) * 100, 2) : 0
            ];
        }

        return response()->json($report);
    }
}
