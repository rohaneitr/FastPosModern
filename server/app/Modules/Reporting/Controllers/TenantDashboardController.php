<?php

namespace App\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TenantDashboardController extends Controller
{
    /**
     * Get real-time dashboard statistics with caching.
     */
    public function stats(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $locationId = auth()->user()->current_location_id ?? null;

        // Cache key based on business, location, and date (cache for 5 mins)
        $cacheKey = "dashboard_stats_{$businessId}_" . ($locationId ?? 'all') . "_" . date('Y-m-d');
        
        $stats = Cache::remember($cacheKey, 300, function () use ($businessId, $locationId) {
            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();

            // 1. Sales & Revenue
            $salesQuery = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('type', 'sell')
                ->where('status', 'final');
                
            if ($locationId) {
                $salesQuery->where('location_id', $locationId);
            }

            $todaySales = (clone $salesQuery)->whereDate('transaction_date', $today)->sum('final_total');
            $monthlySales = (clone $salesQuery)->whereBetween('transaction_date', [$startOfMonth, Carbon::now()])->sum('final_total');
            $todayOrders = (clone $salesQuery)->whereDate('transaction_date', $today)->count();

            // 2. Low Stock Alerts
            $stockQuery = DB::table('inventory_layers')
                ->join('products', 'inventory_layers.product_id', '=', 'products.id')
                ->where('inventory_layers.business_id', $businessId)
                ->groupBy('products.id', 'products.name', 'products.alert_quantity', 'products.image')
                ->select(
                    'products.id', 
                    'products.name', 
                    'products.alert_quantity', 
                    'products.image',
                    DB::raw('SUM(inventory_layers.remaining_qty) as total_qty')
                )
                ->havingRaw('SUM(inventory_layers.remaining_qty) <= products.alert_quantity')
                ->limit(10);
            $lowStockItems = $stockQuery->get();

            // 3. Sales Trend (Last 7 Days)
            $salesTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dayTotal = (clone $salesQuery)->whereDate('transaction_date', $date)->sum('final_total');
                $salesTrend[] = [
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->format('D'),
                    'total' => $dayTotal
                ];
            }

            // 4. Top Products (This Month)
            $topProducts = DB::table('transaction_lines')
                ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_lines.product_id', '=', 'products.id')
                ->where('transactions.business_id', $businessId)
                ->where('transactions.type', 'sell')
                ->whereBetween('transactions.transaction_date', [$startOfMonth, Carbon::now()])
                ->select('products.name', DB::raw('SUM(transaction_lines.quantity) as total_sold'), DB::raw('SUM(transaction_lines.unit_price * transaction_lines.quantity) as total_revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderBy('total_sold', 'desc')
                ->limit(5)
                ->get();

            // 5. Recent Transactions
            $recentTransactions = (clone $salesQuery)
                ->join('contacts', 'transactions.contact_id', '=', 'contacts.id', 'left')
                ->select('transactions.id', 'transactions.invoice_no', 'transactions.final_total', 'transactions.payment_status', 'transactions.transaction_date', 'contacts.name as customer_name')
                ->orderBy('transactions.transaction_date', 'desc')
                ->limit(5)
                ->get();

            return [
                'today_sales' => $todaySales,
                'monthly_sales' => $monthlySales,
                'today_orders' => $todayOrders,
                'sales_trend' => $salesTrend,
                'low_stock_items' => $lowStockItems,
                'top_products' => $topProducts,
                'recent_transactions' => $recentTransactions,
            ];
        });

        return response()->json($stats);
    }
}
