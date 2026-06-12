<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * TenantSubscriptionService
 *
 * Extracted from SuperadminController:
 *   - renewSubscription()    (lines 191–227)
 *   - overrideSubscription() (lines 232–261)
 *
 * Handles SuperAdmin-level subscription mutations on behalf of tenants.
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.2
 * @version 2026-06-12
 */
class TenantSubscriptionService
{
    /**
     * Renew a business subscription by extending its end date.
     *
     * @param int    $businessId
     * @param string $duration  '1_month' | '1_year'
     *
     * @return array{subscription_ends_at: string, subscription_status: string}
     *
     * @throws \RuntimeException   If business not found
     * @throws \InvalidArgumentException  If duration is invalid
     */
    public function renew(int $businessId, string $duration): array
    {
        if (!in_array($duration, ['1_month', '1_year'], true)) {
            throw new \InvalidArgumentException("Invalid duration: {$duration}. Must be '1_month' or '1_year'.");
        }

        $business = DB::table('businesses')->where('id', $businessId)->first();
        if (!$business) {
            throw new \RuntimeException('Business not found for ID: ' . $businessId);
        }

        // If subscription has already expired, start from today
        $currentEnd = $business->subscription_ends_at
            ? Carbon::parse($business->subscription_ends_at)
            : now();

        if ($currentEnd->isPast()) {
            $currentEnd = now();
        }

        $newEnd = $duration === '1_month'
            ? $currentEnd->addMonth()
            : $currentEnd->addYear();

        DB::table('businesses')->where('id', $businessId)->update([
            'subscription_ends_at' => $newEnd->format('Y-m-d'),
            'subscription_status'  => 'Active',
            'is_active'            => true,
        ]);

        return [
            'subscription_ends_at' => $newEnd->format('Y-m-d'),
            'subscription_status'  => 'Active',
        ];
    }

    /**
     * Directly override a business's subscription status and optional end date.
     *
     * @param int    $businessId
     * @param string $status   'Active' | 'Expired' | 'Suspended'
     * @param string|null $endsAt  Optional new end date (YYYY-MM-DD)
     *
     * @throws \RuntimeException  If business not found
     */
    public function override(int $businessId, string $status, ?string $endsAt = null): void
    {
        $business = DB::table('businesses')->where('id', $businessId)->first();
        if (!$business) {
            throw new \RuntimeException('Business not found for ID: ' . $businessId);
        }

        $updateData = [
            'subscription_status' => $status,
            'is_active'           => $status === 'Active',
        ];

        if ($endsAt !== null) {
            $updateData['subscription_ends_at'] = $endsAt;
        }

        DB::table('businesses')->where('id', $businessId)->update($updateData);
    }
}
