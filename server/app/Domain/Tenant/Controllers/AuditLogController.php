<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Tenant\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/superadmin/audit-logs
 *
 * Paginated audit trail for the SuperAdmin dashboard.
 * Supports filtering by event type, causer, and subject.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized.');
        }

        $query = AuditLog::orderBy('created_at', 'desc');

        // ── Filters ───────────────────────────────────────────────────────────
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(causer_name) LIKE ?',   ["%{$search}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(subject_label) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // ── Date range ────────────────────────────────────────────────────────
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(50);

        // Attach a list of distinct event types for the frontend filter dropdown
        $eventTypes = AuditLog::select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return response()->json([
            'logs'        => $logs,
            'event_types' => $eventTypes,
        ]);
    }
}
