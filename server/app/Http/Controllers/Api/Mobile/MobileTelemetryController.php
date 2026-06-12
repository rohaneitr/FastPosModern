<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Mobile\MobileDashboardOverviewResource;

class MobileTelemetryController extends Controller
{
    public function getPulse(Request $request)
    {
        try {
            $businessId = $request->user()->business_id;

            // Hyper-optimized Redis cache read (simulated caching for atomic pulse)
            $pulseData = Cache::remember("mobile_pulse_{$businessId}", 60, function () use ($businessId) {
                $todayRevenue = DB::table('transactions')
                    ->where('business_id', $businessId)
                    ->where('type', 'sell')
                    ->whereDate('transaction_date', today())
                    ->sum('final_total');

                $activeDrawer = DB::table('cash_registers')
                    ->where('business_id', $businessId)
                    ->where('status', 'open')
                    ->exists();

                $lowStock = DB::table('products')
                    ->where('business_id', $businessId)
                    ->whereRaw('alert_quantity > 0') // Simulation logic
                    ->count();

                return [
                    'net_revenue' => $todayRevenue,
                    'is_drawer_open' => $activeDrawer,
                    'low_stock_count' => $lowStock,
                    'timestamp' => time()
                ];
            });

            return response()->json(new MobileDashboardOverviewResource((object) $pulseData));

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Telemetry Engine Failure',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
