<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialReportRequest;
use App\Modules\Finance\Queries\GetTenantTrialBalanceAction;
use App\Modules\Finance\Queries\GetProfitAndLossStatementAction;
use App\Modules\Finance\Queries\GetBalanceSheetAction;
use App\Http\Resources\Accounting\TrialBalanceResource;
use App\Http\Resources\Accounting\ProfitAndLossResource;
use App\Http\Resources\Accounting\BalanceSheetResource;

class FinancialReportingController extends Controller
{
    public function getTrialBalance(FinancialReportRequest $request, GetTenantTrialBalanceAction $action)
    {
        $businessId = auth()->user()->business_id;
        $data = $action->execute(
            $businessId,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'data' => TrialBalanceResource::collection($data)
        ]);
    }

    public function getProfitAndLoss(FinancialReportRequest $request, GetProfitAndLossStatementAction $action)
    {
        $businessId = auth()->user()->business_id;
        $data = $action->execute(
            $businessId,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return new ProfitAndLossResource($data);
    }

    public function getBalanceSheet(FinancialReportRequest $request, GetBalanceSheetAction $action)
    {
        $businessId = auth()->user()->business_id;
        
        // A Balance Sheet ONLY cares about the Snapshot Date (as_of_date or end_date).
        // It strictly ignores start_date to ensure GAAP compliance (Cumulative from inception).
        $asOfDate = $request->input('as_of_date') ?? $request->input('end_date');

        $data = $action->execute(
            $businessId,
            $asOfDate
        );

        return new BalanceSheetResource($data);
    }
}
