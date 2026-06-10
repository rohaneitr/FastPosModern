<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Zero-Trust Validation
        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'status' => 'nullable|string', // Event mapping
            'date_range' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
        ]);

        // CRITICAL: Scope Bypass
        $query = AuditLog::withoutGlobalScopes()->with(['business:id,name', 'user:id,first_name,last_name,email']);

        // Filtering
        if (!empty($validated['tenant_id'])) {
            $query->where('business_id', $validated['tenant_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('event', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('auditable_type', 'LIKE', '%' . $validated['search'] . '%')
                  ->orWhere('ip_address', 'LIKE', '%' . $validated['search'] . '%')
                  ->orWhereHas('user', function($uq) use ($validated) {
                      $uq->where('first_name', 'LIKE', '%' . $validated['search'] . '%')
                         ->orWhere('last_name', 'LIKE', '%' . $validated['search'] . '%')
                         ->orWhere('email', 'LIKE', '%' . $validated['search'] . '%');
                  });
            });
        }

        if (!empty($validated['date_range'])) {
            $dates = explode(',', $validated['date_range']);
            if (count($dates) === 2) {
                $query->whereBetween('created_at', [$dates[0] . ' 00:00:00', $dates[1] . ' 23:59:59']);
            }
        }

        // Performance: Pagination
        $logs = $query->orderBy('id', 'desc')->paginate(50);
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'causer_name' => $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) : 'System',
                'event' => $log->event,
                'description' => "Performed {$log->event} on {$log->auditable_type}",
                'subject_type' => class_basename($log->auditable_type),
                'subject_id' => $log->auditable_id,
                'subject_label' => $log->business ? $log->business->name : null,
                'ip_address' => $log->ip_address,
                'properties' => [
                    'old' => $log->old_values,
                    'new' => $log->new_values,
                ],
                'created_at' => $log->created_at,
            ];
        });

        // Extract distinct events for the frontend dropdown
        $eventTypes = AuditLog::withoutGlobalScopes()->select('event')->distinct()->pluck('event');

        return response()->json([
            'logs' => $logs,
            'event_types' => $eventTypes
        ]);
    }
}
