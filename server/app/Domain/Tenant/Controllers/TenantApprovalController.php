<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Tenant\Models\TenantRequest;
use App\Domain\Tenant\Models\Plan;
use App\Domain\Tenant\Models\Business;
use App\Domain\Tenant\Actions\ProvisionSubscriptionAction;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Services\AuditLogger;
use App\Mail\TenantApprovedMail;
use App\Mail\TenantRejectedMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TenantApprovalController extends Controller
{
    // ─── Queue List ───────────────────────────────────────────────────────────

    /**
     * GET /api/v1/superadmin/tenant-requests
     *
     * Returns all tenant requests, defaulting to pending first.
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireSuperAdmin($request);

        $query = TenantRequest::with(['plan', 'reviewer'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->whereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"]);
        }

        $requests = $query->paginate(20);

        return response()->json($requests);
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/superadmin/tenant-requests/{id}/approve
     *
     * 1. Creates the Business and provisions subscription + license.
     * 2. Updates the request record.
     * 3. Queues TenantApprovedMail to the applicant.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $this->requireSuperAdmin($request);

        $request->validate([
            'subdomain' => 'nullable|string|unique:businesses,subdomain|regex:/^[a-zA-Z0-9\-]+$/',
            'custom_domain' => 'nullable|string|unique:businesses,custom_domain'
        ]);

        $tenantRequest = TenantRequest::findOrFail($id);

        if (!$tenantRequest->isPending()) {
            return response()->json([
                'message' => "Request has already been {$tenantRequest->status}."
            ], 422);
        }

        $plan = Plan::findOrFail($tenantRequest->plan_id);

        // Generate a temporary password for the owner account
        $temporaryPassword = $this->generateTemporaryPassword();
        $licenseKey        = null;

        DB::transaction(function () use ($tenantRequest, $plan, $request, $temporaryPassword, &$licenseKey) {
            // 1. Create owner user if not already existing.
            $ownerEmail = $tenantRequest->applicant_email
                ?? ($tenantRequest->business_name . '@tenant.fastpos.app');

            $owner = User::firstOrCreate(
                ['email' => $ownerEmail],
                [
                    'first_name' => $tenantRequest->applicant_name ?? $tenantRequest->business_name,
                    'last_name'  => '',
                    'password'   => bcrypt($temporaryPassword),
                ]
            );

            // 2. Create the Business record.
            $business = Business::create([
                'name'      => $tenantRequest->business_name,
                'owner_id'  => $owner->id,
                'subdomain' => $request->subdomain ?? null,
                'custom_domain' => $request->custom_domain ?? null,
                'is_active' => true,
            ]);

            // Bind the owner to the business
            $owner->update(['business_id' => $business->id]);
            $owner->assignRole('BusinessAdmin');

            // 3. Provision subscription (and license if hybrid/mobile plan).
            $provisionAction = new ProvisionSubscriptionAction();
            $provisionAction->execute($business->id, $plan);

            // Grab the license key if one was just created
            $license = DB::table('licenses')
                ->where('tenant_id', $business->id)
                ->orderByDesc('id')
                ->first();
            $licenseKey = $license?->license_key;

            // 4. Update the request record.
            $tenantRequest->update([
                'tenant_id'   => $business->id,
                'status'      => TenantRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        $tenantRequest->load(['plan', 'reviewer']);

        // ── Audit trail ────────────────────────────────────────────────────────
        $freshBusiness = Business::find($tenantRequest->tenant_id);
        if ($freshBusiness) {
            AuditLogger::tenantApproved($request->user(), $freshBusiness, $plan->name, $licenseKey);
        }

        // ── Queue welcome email ────────────────────────────────────────────────
        $recipientEmail = $tenantRequest->applicant_email;
        if ($recipientEmail) {
            try {
                Mail::to($recipientEmail)->queue(new TenantApprovedMail(
                    businessName:      $tenantRequest->business_name,
                    ownerEmail:        $recipientEmail,
                    temporaryPassword: $temporaryPassword,
                    planName:          $plan->name,
                    licenseKey:        $licenseKey,
                ));
            } catch (\Throwable $e) {
                Log::error('TenantApprovedMail queue failed', [
                    'tenant_request_id' => $tenantRequest->id,
                    'error'             => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Tenant approved and fully provisioned. Welcome email queued.',
            'request' => $tenantRequest,
        ]);
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/superadmin/tenant-requests/{id}/reject
     *
     * 1. Marks the request rejected with a mandatory reason.
     * 2. Queues TenantRejectedMail to the applicant.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $this->requireSuperAdmin($request);

        $tenantRequest = TenantRequest::findOrFail($id);

        if (!$tenantRequest->isPending()) {
            return response()->json([
                'message' => "Request has already been {$tenantRequest->status}."
            ], 422);
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:10|max:1000',
        ]);

        $tenantRequest->update([
            'status'           => TenantRequest::STATUS_REJECTED,
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $tenantRequest->load(['plan', 'reviewer']);

        // ── Audit trail ────────────────────────────────────────────────────────
        AuditLogger::tenantRejected($request->user(), $tenantRequest, $request->rejection_reason);

        // ── Queue rejection notification ───────────────────────────────────────
        $recipientEmail = $tenantRequest->applicant_email;
        if ($recipientEmail) {
            try {
                Mail::to($recipientEmail)->queue(new TenantRejectedMail(
                    businessName:    $tenantRequest->business_name,
                    rejectionReason: $request->rejection_reason,
                ));
            } catch (\Throwable $e) {
                Log::error('TenantRejectedMail queue failed', [
                    'tenant_request_id' => $tenantRequest->id,
                    'error'             => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Tenant request rejected. Notification email queued.',
            'request' => $tenantRequest,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function requireSuperAdmin(Request $request): void
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized.');
        }
    }

    /**
     * Generate a cryptographically strong temporary password.
     * Format: 3 char groups separated by dashes — easy to read in email.
     * e.g.  Kx7!mQ2#pR
     */
    private function generateTemporaryPassword(): string
    {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // no I/O confusion
        $lower   = 'abcdefghjkmnpqrstuvwxyz';   // no l/o confusion
        $digits  = '23456789';                   // no 0/1 confusion
        $symbols = '!@#$%&*';

        // Guarantee at least one of each character class
        $password  = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        $all = $upper . $lower . $digits . $symbols;
        for ($i = 4; $i < 12; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle to avoid predictable class ordering
        return str_shuffle($password);
    }
}
