<?php

namespace App\Modules\Tenant\Actions;

use App\Modules\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Mail\TenantWelcomeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * CreateNewTenantAction — Single Responsibility: Atomic Tenant Provisioning
 *
 * Extracted from SuperadminController::storeBusiness() (lines 263–347).
 *
 * This action encapsulates the full tenant onboarding pipeline:
 *   1. Create or find the owner user
 *   2. Create the Business record
 *   3. Link user → business, assign BusinessAdmin role
 *   4. Provision subscription via ProvisionSubscriptionAction
 *   5. Retrieve the generated license key (if any)
 *   6. Queue the welcome email (non-blocking — failure is logged only)
 *
 * WHY AN ACTION CLASS?
 * - The entire pipeline must be atomic (wrapped in a DB transaction)
 * - The controller should not know about user creation, role assignment,
 *   or subscription provisioning — these are domain concerns
 * - This action can be reused from CLI commands (e.g., seeding, imports)
 *
 * ZERO TRUST:
 * - Uniqueness constraints are enforced by the DB, not just by the validator
 * - Password is bcrypt-hashed here, never stored plain
 * - Plain-text password is passed to email only (one-time display), never persisted
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.2
 * @version 2026-06-12
 */
final class CreateNewTenantAction
{
    public function __construct(
        private readonly ProvisionSubscriptionAction $provisionSubscription,
    ) {}

    /**
     * Execute the atomic tenant provisioning pipeline.
     *
     * @param array $data {
     *   name:          string  — Business name
     *   owner_email:   string  — Owner's email
     *   password:      string  — Plain-text temporary password (will be bcrypt'd)
     *   plan_id:       int     — Plan ID (must exist in plans table)
     *   subdomain:     ?string — Optional subdomain
     *   custom_domain: ?string — Optional custom domain
     * }
     *
     * @return array{business: Business, license_key: ?string, plain_password: string}
     *
     * @throws \Throwable — Wraps any DB or provisioning failure; caller must handle
     */
    public function execute(array $data): array
    {
        $plainPassword = $data['password'];

        $result = DB::transaction(function () use ($data, $plainPassword) {

            // ── 1. Find or create the owner user ─────────────────────────────
            $user = User::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'first_name' => 'Tenant',
                    'last_name'  => 'Owner',
                    'password'   => bcrypt($plainPassword),
                ]
            );

            // ── 2. Create the Business record ─────────────────────────────────
            $business = Business::create([
                'name'          => $data['name'],
                'owner_id'      => $user->id,
                'subdomain'     => $data['subdomain'] ?? null,
                'custom_domain' => $data['custom_domain'] ?? null,
                'time_zone'     => 'Asia/Dhaka',
                'is_active'     => true,
                'status'        => 'pending_activation',
                'license_key'   => null,
                'active_modules' => [],
                'trial_ends_at' => now()->addDays(30),
            ]);

            // ── 3. Link user → business, assign role ──────────────────────────
            $user->update(['business_id' => $business->id]);
            $user->assignRole('BusinessAdmin');

            // ── 4. Provision subscription (+ modules + permissions + settings) ─
            $plan = Plan::findOrFail($data['plan_id']);
            $this->provisionSubscription->execute($business->id, $plan);

            // ── 5. Retrieve license key if created during provisioning ─────────
            $license = DB::table('licenses')
                ->where('tenant_id', $business->id)
                ->orderByDesc('id')
                ->first();

            return [
                'business'   => $business->fresh(),
                'licenseKey' => $license?->license_key,
            ];
        });

        // ── 6. Queue welcome email — NON-BLOCKING ─────────────────────────────
        // Email failure must NEVER roll back the transaction.
        $this->dispatchWelcomeEmail(
            $data['owner_email'],
            $data['name'],
            $plainPassword,
            $data['plan_id'],
            $result['licenseKey'],
        );

        return [
            'business'       => $result['business'],
            'license_key'    => $result['licenseKey'],
            'plain_password' => $plainPassword,
        ];
    }

    /**
     * Dispatch the welcome email asynchronously.
     * Catches all throwables — a mail failure must NEVER crash provisioning.
     */
    private function dispatchWelcomeEmail(
        string  $email,
        string  $businessName,
        string  $plainPassword,
        int     $planId,
        ?string $licenseKey,
    ): void {
        try {
            $plan = DB::table('plans')->where('id', $planId)->first();
            Mail::to($email)->queue(new TenantWelcomeMail(
                $businessName,
                $email,
                $plainPassword,   // one-time display password
                $plan->name ?? 'Standard',
                $licenseKey,
            ));
        } catch (\Throwable $e) {
            Log::error('[CreateNewTenantAction] Welcome email dispatch failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            // Intentionally NOT re-throwing — email failure is non-fatal
        }
    }
}
