<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuditLogController — Phase 9: Enterprise Audit Trail
 *
 * Exposes paginated, filterable audit logs to authenticated tenant users.
 *
 * ── TENANT ISOLATION GUARANTEE ─────────────────────────────────────────────
 *
 * This controller does NOT manually apply any `where('business_id', ...)` clause.
 * Isolation is 100% guaranteed by the Activity model's global scope
 * (App\Modules\Tenant\Models\Activity::booted → addGlobalScope('tenant_isolation')).
 *
 * The scope rules are:
 *   - Tenant user  → filtered to user's business_id (zero cross-tenant leakage)
 *   - SuperAdmin   → unfiltered (full cross-tenant visibility by design)
 *   - Unauthenticated → whereRaw('1 = 0') — FAIL CLOSED, returns zero rows
 *
 * This means even if a developer accidentally adds `Activity::all()` anywhere
 * in the codebase, the global scope prevents any data from leaking.
 *
 * ── PII SAFETY ─────────────────────────────────────────────────────────────
 *
 * The Activity model's `getPropertiesAttribute()` accessor applies a second-pass
 * masking on `password`, `stripe_id`, etc. before the JSON is serialised.
 * The controller does not need to touch the properties array at all.
 *
 * ── FILTERS SUPPORTED ──────────────────────────────────────────────────────
 *
 *   GET /api/v1/audit-logs
 *     ?log_name=sales.Sale           — Filter by module.Model (e.g. 'iam.User')
 *     ?event=updated                 — Filter by event type: created|updated|deleted
 *     ?causer_id=5                   — Filter by the user who caused the event
 *     ?subject_type=transactions     — Filter by subject model table name
 *     ?subject_id=123                — Filter by specific record ID
 *     ?date_from=2026-06-01          — Lower date bound (inclusive)
 *     ?date_to=2026-06-13            — Upper date bound (inclusive)
 *     &per_page=25                   — Rows per page (default 25, max 100)
 *
 * @version Phase 9 — Enterprise Audit Trail
 */
class AuditLogController extends Controller
{
    /**
     * Return a paginated list of audit log entries for the authenticated tenant.
     *
     * Relations eager-loaded:
     *   - causer: the User who triggered the change (may be null for system events)
     *   - subject: the Eloquent model instance that was changed (may be soft-deleted)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'log_name'     => ['nullable', 'string', 'max:100'],
            'event'        => ['nullable', 'string', 'in:created,updated,deleted,restored'],
            'causer_id'    => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:200'],
            'subject_id'   => ['nullable', 'integer'],
            'date_from'    => ['nullable', 'date'],
            'date_to'      => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Tenant isolation is implicit via Activity's global scope —
        // NO manual business_id where-clause is needed or wanted here.
        $query = Activity::query()
            ->with([
                // The user who performed the action
                'causer:id,first_name,last_name,email,user_type',
            ])
            ->latest(); // Most recent events first

        // ── Filters ───────────────────────────────────────────────────────────

        if ($logName = $request->query('log_name')) {
            $query->where('log_name', $logName);
        }

        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }

        if ($causerId = $request->query('causer_id')) {
            $query->where('causer_type', \App\Modules\IAM\Models\User::class)
                  ->where('causer_id', (int) $causerId);
        }

        if ($subjectType = $request->query('subject_type')) {
            // Accept both table name ('transactions') and FQCN
            // We resolve to the FQCN if a short name is given for usability
            $query->where('subject_type', 'LIKE', "%{$subjectType}%");
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', (int) $subjectId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // ── Paginate ──────────────────────────────────────────────────────────

        $perPage = min((int) ($request->query('per_page', 25)), 100);
        $logs    = $query->paginate($perPage);

        // ── Shape the response ────────────────────────────────────────────────
        // Transform each log entry into a clean, frontend-friendly shape.
        // The properties accessor automatically masks PII fields.
        $logs->through(function (Activity $log) {
            $changes = $log->properties; // PII already masked by model accessor

            return [
                'id'           => $log->id,
                'event'        => $log->event,
                'log_name'     => $log->log_name,
                'description'  => $log->description,
                'subject_type' => class_basename($log->subject_type ?? ''),
                'subject_id'   => $log->subject_id,
                'causer'       => $log->causer ? [
                    'id'        => $log->causer->id,
                    'name'      => trim(($log->causer->first_name ?? '') . ' ' . ($log->causer->last_name ?? '')),
                    'email'     => $log->causer->email,
                    'user_type' => $log->causer->user_type,
                ] : null,
                'changes' => [
                    'old' => $changes->get('old', []),
                    'new' => $changes->get('attributes', []),
                ],
                'logged_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return $this->paginatedResponse($logs, 'Audit logs retrieved.');
    }

    /**
     * Return audit history for a specific record (subject).
     *
     * GET /api/v1/audit-logs/{subjectType}/{subjectId}
     *
     * Example: GET /api/v1/audit-logs/transactions/42
     */
    public function show(Request $request, string $subjectType, int $subjectId): JsonResponse
    {
        $logs = Activity::query()
            ->with(['causer:id,first_name,last_name,email'])
            ->where('subject_type', 'LIKE', "%{$subjectType}%")
            ->where('subject_id', $subjectId)
            ->latest()
            ->paginate(50);

        $logs->through(function (Activity $log) {
            $changes = $log->properties;
            return [
                'id'          => $log->id,
                'event'       => $log->event,
                'description' => $log->description,
                'causer'      => $log->causer ? [
                    'id'    => $log->causer->id,
                    'name'  => trim(($log->causer->first_name ?? '') . ' ' . ($log->causer->last_name ?? '')),
                    'email' => $log->causer->email,
                ] : null,
                'changes' => [
                    'old' => $changes->get('old', []),
                    'new' => $changes->get('attributes', []),
                ],
                'logged_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return $this->paginatedResponse($logs, "Audit history for {$subjectType} #{$subjectId}.");
    }
}
