<?php

namespace App\Domain\Analytics\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Domain\Analytics\Services\ModuleAnalyticsAggregator;

class UnifiedAnalyticsController extends Controller
{
    protected $aggregator;

    public function __construct(ModuleAnalyticsAggregator $aggregator)
    {
        $this->aggregator = $aggregator;
    }

    public function getConsolidatedOverview(Request $request)
    {
        $businessId = $request->user()->business_id;

        // Fetch active modules directly from Redis Cache to prevent cold starts
        $activeModules = Cache::remember("tenant_modules:{$businessId}", 86400, function () use ($businessId) {
            return DB::table('tenant_modules')
                ->join('modules', 'tenant_modules.module_id', '=', 'modules.id')
                ->where('tenant_modules.business_id', $businessId)
                ->where('tenant_modules.is_active', true)
                ->where(function($query) {
                    $query->whereNull('tenant_modules.expires_at')
                          ->orWhere('tenant_modules.expires_at', '>', now());
                })
                ->pluck('modules.slug')
                ->toArray();
        });

        // Resolve Global metrics
        // In a real scenario, this would query Core POS tables.
        $globalRevenue = 14500;
        $globalProfit = 4200;

        // Isolate module metrics safely
        $moduleMetrics = $this->aggregator->aggregate($activeModules, $businessId);

        return response()->json([
            'global' => [
                'revenue' => $globalRevenue,
                'profit' => $globalProfit
            ],
            'modules' => $moduleMetrics
        ]);
    }
}
