<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Tenant\Models\Activity;

class LogController extends Controller
{
    /**
     * Retrieve audit logs for the authenticated business.
     * The Activity model inherently scopes queries to the business_id
     * via its Global Scope.
     */
    public function index(Request $request)
    {
        $logs = Activity::with('causer')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
