<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reporting\Services\ConsolidatedAnalyticsEngine;

class AnalyticsController extends Controller
{
    protected ConsolidatedAnalyticsEngine $engine;

    public function __construct(ConsolidatedAnalyticsEngine $engine)
    {
        $this->engine = $engine;
    }

    public function overview(Request $request)
    {
        $businessId = $request->user()->business_id;

        $payload = $this->engine->getDashboardPayload($businessId);

        return response()->json($payload);
    }
}
