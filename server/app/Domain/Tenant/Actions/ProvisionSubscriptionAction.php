<?php

namespace App\Domain\Tenant\Actions;

use App\Domain\Tenant\Models\Plan;
use App\Domain\Tenant\Models\Subscription;
use App\Domain\Tenant\Models\License;
use App\Domain\Tenant\Services\LicenseKeyService;
use Carbon\Carbon;

class ProvisionSubscriptionAction
{
    /**
     * Execute subscription provisioning and (for hybrid/mobile plans)
     * generate a cryptographically signed ECDSA license token.
     *
     * @param  int         $tenantId
     * @param  Plan        $plan
     * @param  Carbon|null $customExpiryDate
     * @param  string|null $hardwareHash     Optional device fingerprint to bind into the token
     * @return array{ subscription: Subscription, license_key: string|null }
     */
    public function execute(
        int     $tenantId,
        Plan    $plan,
        ?Carbon $customExpiryDate = null,
        ?string $hardwareHash     = null,
    ): array {
        $expiresAt = $customExpiryDate ?? ($plan->interval === 'year'
            ? now()->addYear()
            : now()->addMonth()
        );

        // ── 1. Create / update the subscription record ────────────────────────
        $subscription = Subscription::updateOrCreate(
            ['business_id' => $tenantId],
            [
                'plan_id'              => $plan->id,
                'status'               => 'active',
                'current_period_start' => now(),
                'current_period_end'   => $expiresAt,
            ]
        );

        // ── 2. Generate ECDSA license for hybrid / mobile plans ───────────────
        $licenseKey = null;
        $planType   = $plan->plan_type ?? 'web';

        if (in_array($planType, ['online_web', 'hybrid_offline_sync', 'mobile_native'], true)) {
            // Map internal plan_type values to the token's canonical type field
            $tokenType = match ($planType) {
                'hybrid_offline_sync' => 'hybrid',
                'mobile_native'       => 'mobile',
                default               => 'web',
            };

            $licenseService = new LicenseKeyService();
            $licenseKey     = $licenseService->generateKey(
                tenantId:     $tenantId,
                planId:       $plan->id,
                type:         $tokenType,
                expiresAt:    Carbon::instance($expiresAt),
                hardwareHash: $hardwareHash,
            );

            // Upsert: if a license already exists for this tenant+plan, renew it
            License::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'plan_id'   => $plan->id,
                ],
                [
                    'license_key'    => $licenseKey,       // TEXT column — holds full signed token
                    'status'         => 'active',
                    'device_limit'   => $plan->device_limit   ?? 1,
                    'employee_limit' => $plan->employee_limit ?? 1,
                    'activated_at'   => now(),
                    'expires_at'     => $expiresAt,
                ]
            );
        }

        return [
            'subscription' => $subscription,
            'license_key'  => $licenseKey,
        ];
    }
}
