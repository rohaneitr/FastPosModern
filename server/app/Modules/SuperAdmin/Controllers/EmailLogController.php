<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        // Zero-Trust Validation
        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'status' => 'nullable|in:sent,failed,queued',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = EmailLog::query();

        // Filtering
        if (!empty($validated['tenant_id'])) {
            $query->where('business_id', $validated['tenant_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('to_email', 'LIKE', '%' . $validated['search'] . '%')
                  ->orWhere('subject', 'LIKE', '%' . $validated['search'] . '%')
                  ->orWhere('mailable_class', 'LIKE', '%' . $validated['search'] . '%');
            });
        }

        // Performance: Pagination
        $logs = $query->orderBy('id', 'desc')->paginate(50);

        return response()->json([
            'logs' => $logs
        ]);
    }

    public function stats(Request $request)
    {
        // Quick aggregated stats
        $total = EmailLog::count();
        $sent = EmailLog::where('status', 'sent')->count();
        $failed = EmailLog::where('status', 'failed')->count();
        $queued = EmailLog::where('status', 'queued')->count();
        $last24h = EmailLog::where('created_at', '>=', now()->subDay())->count();

        return response()->json([
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'queued' => $queued,
            'last_24h' => $last24h,
        ]);
    }
}
