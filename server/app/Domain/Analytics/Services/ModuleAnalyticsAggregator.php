<?php

namespace App\Domain\Analytics\Services;

use Illuminate\Support\Facades\Log;

class ModuleAnalyticsAggregator
{
    /**
     * Consolidate metrics from active modules utilizing a Circuit Breaker pattern.
     */
    public function aggregate(array $activeModules, int $businessId): array
    {
        $aggregated = [];

        foreach ($activeModules as $slug) {
            try {
                // In a real system, we'd resolve a registered interface or provider based on slug
                // Here we simulate the module analytics provider logic.
                $metrics = $this->fetchModuleMetrics($slug, $businessId);
                $aggregated[$slug] = $metrics;
            } catch (\Exception $e) {
                // Circuit Breaker: Isolate the failing module, log it, and continue aggregating the rest
                Log::error("FPM Analytics Circuit Breaker: Module [{$slug}] failed to generate metrics.", [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
                $aggregated[$slug] = null;
            }
        }

        return $aggregated;
    }

    private function fetchModuleMetrics(string $slug, int $businessId): array
    {
        if ($slug === 'pharmacy') {
            return ['revenue' => 6000, 'volume' => 120, 'color' => '#10B981'];
        }

        if ($slug === 'restaurant') {
            return ['revenue' => 8500, 'volume' => 450, 'color' => '#F43F5E'];
        }

        if ($slug === 'buggy-module') {
            throw new \Exception('Simulated module analytics crash.');
        }

        return ['revenue' => 0, 'volume' => 0, 'color' => '#6B7280'];
    }
}
