<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reports\Services\LedgerReportingService;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    private LedgerReportingService $reportingService;

    public function __construct(LedgerReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    public function profitAndLoss(Request $request)
    {
        $businessId = $request->user()->business_id;
        
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        try {
            $data = $this->reportingService->getProfitAndLoss($businessId, $startDate, $endDate);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Financial Integrity Error',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
