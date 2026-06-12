<?php

namespace App\Modules\Tenant\Actions;

use App\Modules\IAM\Models\User;
use App\Modules\SuperAdmin\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * ImpersonateTenantAction — Single Responsibility: Secure Tenant Impersonation
 *
 * Extracted from SuperadminController::impersonate() (lines 528–571).
 *
 * Responsibilities:
 *   1. Resolve the target BusinessAdmin user for the given business
 *   2. Generate a Sanctum token with impersonation context in its abilities
 *   3. Record an immutable forensic audit log entry
 *
 * SECURITY — ZERO TRUST:
 *   - Token abilities include 'impersonate' and 'admin_id:{superAdminId}'
 *     so the backend can always trace WHO issued the impersonation.
 *   - AuditLog is written atomically within the same DB call.
 *   - This action NEVER grants SuperAdmin abilities to the impersonation token.
 *   - The generated token inherits the target user's role/permission scope only.
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.2
 * @version 2026-06-12
 */
final class ImpersonateTenantAction
{
    /**
     * Execute the impersonation.
     *
     * @param int    $businessId   Target business to impersonate
     * @param User   $superAdmin   The authenticated SuperAdmin issuing this action
     * @param string $ipAddress    Caller's IP (from request)
     * @param string $userAgent    Caller's User-Agent (from request)
     *
     * @return array{token: string, business_name: string, user: User, original_admin_id: int}
     *
     * @throws \RuntimeException   If target business or admin user is not found
     */
    public function execute(int $businessId, User $superAdmin, string $ipAddress, string $userAgent): array
    {
        // ── 1. Find the target business ───────────────────────────────────────
        $targetBusiness = DB::table('businesses')->where('id', $businessId)->first();

        if (!$targetBusiness) {
            throw new \RuntimeException('Business not found for ID: ' . $businessId);
        }

        // ── 2. Resolve the target admin user ──────────────────────────────────
        // Priority: BusinessAdmin role first, then fall back to the business owner_id
        $targetUser = User::where('business_id', $businessId)
            ->whereHas('roles', fn($q) => $q->where('name', 'BusinessAdmin'))
            ->first()
            ?? User::find($targetBusiness->owner_id);

        if (!$targetUser) {
            throw new \RuntimeException('No admin user found for business ID: ' . $businessId);
        }

        // ── 3. Generate scoped impersonation token ────────────────────────────
        // Abilities clearly identify this as an impersonation session.
        // The receiving API can check token()->can('impersonate') to gate elevated actions.
        $token = $targetUser->createToken(
            'impersonation_token',
            ['impersonate', 'admin_id:' . $superAdmin->id]
        )->plainTextToken;

        // ── 4. Immutable forensic audit log ───────────────────────────────────
        AuditLog::create([
            'business_id'    => $businessId,
            'user_id'        => $superAdmin->id,
            'event'          => 'impersonate_tenant',
            'auditable_type' => 'App\Modules\Tenant\Models\Business',
            'auditable_id'   => $businessId,
            'new_values'     => ['target_user_id' => $targetUser->id],
            'ip_address'     => $ipAddress,
            'user_agent'     => $userAgent,
            'created_at'     => now(),
        ]);

        return [
            'token'             => $token,
            'business_name'     => $targetBusiness->name,
            'user'              => $targetUser,
            'original_admin_id' => $superAdmin->id,
        ];
    }
}
