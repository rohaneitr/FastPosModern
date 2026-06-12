<?php

namespace App\Modules\Reporting\Controllers;

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

        // Today's sales
        $todaySales = DB::table('transactions')
            ->where('business_id', $businessId)->where('type', 'sell')->where('status', 'final')
            ->whereDate('transaction_date', $today)->sum('final_total');

        $yesterdaySales = DB::table('transactions')
            ->where('business_id', $businessId)->where('type', 'sell')->where('status', 'final')
            ->whereDate('transaction_date', $yesterday)->sum('final_total');

        $salesChange = $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1) : 0;

        // Products sold today
        $productsSold = DB::table('transaction_lines')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->where('transactions.business_id', $businessId)->where('transactions.type', 'sell')->where('transactions.status', 'final')
            ->whereDate('transactions.transaction_date', $today)->sum('transaction_lines.quantity');

        $productsSoldYesterday = DB::table('transaction_lines')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->where('transactions.business_id', $businessId)->where('transactions.type', 'sell')->where('transactions.status', 'final')
            ->whereDate('transactions.transaction_date', $yesterday)->sum('transaction_lines.quantity');

        $productsSoldChange = $productsSoldYesterday > 0 ? round((($productsSold - $productsSoldYesterday) / $productsSoldYesterday) * 100, 1) : 0;

        // Total customers
        $totalCustomers = DB::table('contacts')
            ->where('business_id', $businessId)->whereNull('deleted_at')
            ->whereIn('type', ['customer', 'both'])->count();

        $newCustomersThisWeek = DB::table('contacts')
            ->where('business_id', $businessId)->whereNull('deleted_at')
            ->whereIn('type', ['customer', 'both'])
            ->where('created_at', '>=', Carbon::now()->subWeek())->count();

        // Low stock alerts (qty < 10)
        $lowStockCount = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->where('products.business_id', $businessId)->whereNull('products.deleted_at')
            ->where('product_stocks.qty_available', '<', 10)->count();

        // Top 5 selling products (last 30 days)
        $topProducts = DB::table('transaction_lines')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_lines.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)->where('transactions.type', 'sell')->where('transactions.status', 'final')
            ->where('transactions.transaction_date', '>=', Carbon::now()->subDays(30))
            ->select('products.name', DB::raw('SUM(transaction_lines.quantity) as qty_sold'), DB::raw('SUM(transaction_lines.quantity * transaction_lines.unit_price) as revenue'))
            ->groupBy('products.name')->orderByDesc('qty_sold')->limit(5)->get();

        // 7-day sales trend
        $salesTrend = DB::table('transactions')
            ->where('business_id', $businessId)->where('type', 'sell')->where('status', 'final')
            ->where('transaction_date', '>=', Carbon::now()->subDays(7))
            ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(final_total) as total'), DB::raw('COUNT(id) as count'))
            ->groupBy('date')->orderBy('date')->get();

        // Recent 5 transactions
        $recentTransactions = DB::table('transactions')
            ->leftJoin('users', 'transactions.created_by', '=', 'users.id')
            ->where('transactions.business_id', $businessId)->where('transactions.type', 'sell')
            ->select('transactions.invoice_no', 'transactions.transaction_date', 'transactions.final_total', 'transactions.status',
                DB::raw("users.first_name || ' ' || users.last_name as cashier_name"))
            ->orderByDesc('transactions.transaction_date')->limit(5)->get();

        return response()->json([
            'today_sales' => round($todaySales, 2),
            'sales_change_pct' => $salesChange,
            'products_sold' => (int) $productsSold,
            'products_sold_change_pct' => $productsSoldChange,
            'total_customers' => $totalCustomers,
            'new_customers_this_week' => $newCustomersThisWeek,
            'low_stock_count' => $lowStockCount,
            'top_products' => $topProducts,
            'sales_trend' => $salesTrend,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Get Profit & Loss Summary
     */
    public function profitLoss(Request $request)
    {
        $businessId = $request->user()->business_id;

        // Total Sales (excluding tax)
        $totalSales = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->sum('total_before_tax');

        // Total Sell Returns
        $totalSellReturns = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell_return')
            ->where('status', 'final')
            ->sum('total_before_tax');

        // Net Sales
        $netSales = $totalSales - $totalSellReturns;

        // Total Purchases (excluding tax)
        $totalPurchases = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'purchase')
            ->where('status', 'received')
            ->sum('total_before_tax');

        // Total Expenses
        $totalExpenses = DB::table('expenses')
            ->where('business_id', $businessId)
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
            ->where('business_id', $businessId)
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
     * Export Sales Report as PDF (OOM Safe using chunking)
     */
    public function exportPdf(Request $request)
    {
        $businessId = $request->user()->business_id;
        $businessName = $request->user()->business->name ?? 'FastPOS Business';
        
        $startDate = $request->query('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->query('end_date', Carbon::now()->endOfDay());

        // We use chunking to avoid OOM errors on massive datasets.
        // Instead of passing thousands of raw models to DOMPDF (which will crash it),
        // we aggregate the data during chunking to build a summary.
        $summary = [
            'total_sales' => 0,
            'total_tax' => 0,
            'total_items' => 0,
            'daily_breakdown' => []
        ];

        DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('transaction_date', 'total_before_tax', 'tax_amount', 'final_total')
            ->orderBy('transaction_date', 'asc')
            ->chunk(500, function ($transactions) use (&$summary) {
                foreach ($transactions as $tx) {
                    $summary['total_sales'] += $tx->final_total;
                    $summary['total_tax'] += $tx->tax_amount;
                    $summary['total_items'] += 1;

                    $dateKey = Carbon::parse($tx->transaction_date)->format('Y-m-d');
                    if (!isset($summary['daily_breakdown'][$dateKey])) {
                        $summary['daily_breakdown'][$dateKey] = 0;
                    }
                    $summary['daily_breakdown'][$dateKey] += $tx->final_total;
                }
            });

        // Use Barryvdh\DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.sales-pdf', [
            'summary' => $summary,
            'startDate' => Carbon::parse($startDate)->format('M d, Y'),
            'endDate' => Carbon::parse($endDate)->format('M d, Y'),
            'businessName' => $businessName,
        ]);

        return $pdf->download('sales_report.pdf');
    }

    /**
     * Get Inventory Valuation Report
     */
    public function inventoryValuation(Request $request)
    {
        $businessId = $request->user()->business_id;

        $valuation = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.business_id', $businessId)
            ->whereNull('products.deleted_at')
            ->select(
                'products.name',
                'products.sku',
                'brands.name as brand',
                'categories.name as category',
                'product_stocks.qty_available',
                'products.purchase_price',
                DB::raw('(product_stocks.qty_available * products.purchase_price) as total_value')
            )
            ->where('product_stocks.qty_available', '>', 0)
            ->orderByDesc('total_value')
            ->get();

        return response()->json($valuation);
    }
}
