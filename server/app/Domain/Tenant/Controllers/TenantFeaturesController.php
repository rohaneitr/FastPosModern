<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Tenant\Models\Business;
use App\Domain\Tenant\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * TenantFeaturesController  (Phase 4 – Per-Tenant Module Toggler)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * GET  /api/v1/superadmin/businesses/{id}/features  → show current flags
 * PUT  /api/v1/superadmin/businesses/{id}/features  → replace flags
 */
class TenantFeaturesController extends Controller
{
    /**
     * The canonical module registry with human-readable labels.
     * Add/remove entries here to extend the system.
     */
    public const MODULE_REGISTRY = [
        'pos'               => ['label' => 'Point of Sale',          'description' => 'Core POS terminal and checkout'],
        'inventory'         => ['label' => 'Inventory Management',   'description' => 'Stock tracking, adjustments, transfers'],
        'inventory_sync'    => ['label' => 'Inventory Sync',         'description' => 'Cross-location real-time stock sync'],
        'advanced_hr'       => ['label' => 'Advanced HR',            'description' => 'Payroll, attendance, leave management'],
        'crm'               => ['label' => 'CRM & Contacts',         'description' => 'Customer relationship management'],
        'accounting'        => ['label' => 'Accounting',             'description' => 'Expense tracking and P&L reporting'],
        'multi_location'    => ['label' => 'Multi-Location',         'description' => 'Manage multiple branches'],
        'mobile_api'        => ['label' => 'Mobile API',             'description' => 'Native mobile app data bridge'],
        'offline_sync'      => ['label' => 'Offline Sync',           'description' => 'Hybrid offline-first data sync'],
        'advanced_reports'  => ['label' => 'Advanced Reports',       'description' => 'Custom report builder and exports'],
        'api_access'        => ['label' => 'Public API Access',      'description' => 'External REST API integration'],
        'whitelabel'        => ['label' => 'White-label Branding',   'description' => 'Custom logo, domain, and colours'],
        'pharmacy'          => ['label' => 'Pharmacy Module',        'description' => 'Medicine inventory and prescription management'],
        'quotations'        => ['label' => 'Quotations',             'description' => 'Draft and manage quotes before final sales'],
        'warranty'          => ['label' => 'Warranty & Serials',     'description' => 'Managing item warranties and serial numbers'],
        'pc_builder'        => ['label' => 'PC Builder Engine',      'description' => 'Specialized component builder for computer shops'],
        'cctv_builder'      => ['label' => 'CCTV Builder Engine',    'description' => 'Specialized quota builder for security camera setups'],
        'sms_gateway'       => ['label' => 'SMS/WhatsApp Gateway',   'description' => 'Automated messaging and receipts'],
    ];

    // ── GET /api/v1/superadmin/businesses/{id}/features ───────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $this->guard($request);

        $business = Business::findOrFail($id);
        $stored   = $business->enabled_modules ?? [];

        // Merge stored flags with registry so the response always lists ALL modules
        $modules = collect(self::MODULE_REGISTRY)->map(function ($meta, $key) use ($stored) {
            return [
                'key'         => $key,
                'label'       => $meta['label'],
                'description' => $meta['description'],
                'enabled'     => $stored[$key] ?? true, // default: all enabled unless explicitly disabled
            ];
        })->values();

        return response()->json([
            'business_id'   => $business->id,
            'business_name' => $business->name,
            'modules'       => $modules,
        ]);
    }

    // ── PUT /api/v1/superadmin/businesses/{id}/features ───────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guard($request);

        $request->validate([
            'modules'   => 'required|array',
            'modules.*' => 'boolean',
        ]);

        // Only accept known module keys — silently discard unknown ones
        $incoming = collect($request->modules)
            ->filter(fn ($_, $key) => array_key_exists($key, self::MODULE_REGISTRY))
            ->map(fn ($v) => (bool) $v)
            ->all();

        $business = Business::findOrFail($id);
        $before   = $business->enabled_modules ?? [];

        $business->update(['enabled_modules' => $incoming]);

        // ── Audit trail ────────────────────────────────────────────────────────
        AuditLogger::modulesUpdated($request->user(), $business, $before, $incoming);

        // Return the full module list (same shape as show())
        return $this->show($request, $id);
    }

    // ── Helper: middleware-style guard ────────────────────────────────────────

    private function guard(Request $request): void
    {
        if (!$request->user()?->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized.');
        }
    }
}
