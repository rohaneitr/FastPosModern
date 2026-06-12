<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use App\Modules\Tenant\Actions\ValidatePlanTransitionAction;
use App\Modules\Tenant\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SubscriptionLifecycleService
 *
 * Extracted from SubscriptionController (lines 140–347):
 *   - renew()              — extend subscription + reactivate business
 *   - overrideStatus()     — directly set subscription status + sync business.is_active
 *   - updateCapabilities() — modify limit/module overrides
 *   - changePlan()         — self-service plan migration with policy validation
 *
 * DESIGN DECISIONS:
 *   - Every mutation is wrapped in DB::transaction() inside the service (not the controller)
 *   - AuditLogger calls remain in the service — they're domain events, not HTTP concerns
 *   - The $actor (User) is passed in so the service is decoupled from Request
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.3
 * @version 2026-06-12
 */
class SubscriptionLifecycleService
{
    public function __construct(
        private readonly ValidatePlanTransitionAction $planTransitionValidator,
    ) {}

    /**
     * Extend a subscription's end date by 1 month or 1 year.
     * Reactivates the business if it was suspended due to expiry.
     *
     * @param int    $subscriptionId
     * @param string $period         '1_month' | '1_year'
     * @param mixed  $actor          Authenticated User performing this action
     *
     * @return Subscription  The refreshed subscription record
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function renew(int $subscriptionId, string $period, mixed $actor): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $period, $actor) {
            $subscription = Subscription::with('business')->findOrFail($subscriptionId);

            $oldEnd     = $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)
                : now();
            $currentEnd = $oldEnd->copy();

            // If expired, start renewal from today
            if ($currentEnd->isPast()) {
                $currentEnd = now();
            }

            $newEnd = $period === '1_month'
                ? $currentEnd->addMonth()
                : $currentEnd->addYear();

            $subscription->update([
                'current_period_end' => $newEnd,
                'status'             => 'active',
            ]);

            // Cascade reactivation to the business record
            if ($subscription->business) {
                $subscription->business->update([
                    'is_active'            => true,
                    'subscription_ends_at' => $newEnd->format('Y-m-d'),
                    'subscription_status'  => 'Active',
                ]);
            }

            AuditLogger::subscriptionRenewed(
                $actor,
                $subscription,
                $oldEnd->toDateTimeString(),
                $newEnd->toDateTimeString(),
                $period
            );

            return $subscription->fresh();
        });
    }

    /**
     * Directly override a subscription's status.
     * Cascades is_active to the linked business.
     *
     * @param int    $subscriptionId
     * @param string $newStatus   'active' | 'suspended' | 'canceled' | 'past_due'
     * @param mixed  $actor
     *
     * @return Subscription
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function overrideStatus(int $subscriptionId, string $newStatus, mixed $actor): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $newStatus, $actor) {
            $subscription = Subscription::with('business')->findOrFail($subscriptionId);
            $oldStatus    = $subscription->status;

            $subscription->update(['status' => $newStatus]);

            // Cascade to business.is_active for login enforcement
            if ($subscription->business) {
                $isActive = $newStatus === 'active';
                $subscription->business->update([
                    'is_active'           => $isActive,
                    'subscription_status' => $isActive ? 'Active' : ucfirst($newStatus),
                ]);
            }

            AuditLogger::subscriptionStatusOverridden($actor, $subscription, $oldStatus, $newStatus);

            return $subscription->fresh();
        });
    }

    /**
     * Update limit and module overrides for a specific subscription.
     *
     * @param int   $subscriptionId
     * @param array $limitOverrides   e.g. ['max_users' => 50, 'max_devices' => 10]
     * @param array $moduleOverrides  e.g. ['added' => ['crm'], 'removed' => ['clinic']]
     * @param mixed $actor
     *
     * @return Subscription
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateCapabilities(int $subscriptionId, array $limitOverrides, array $moduleOverrides, mixed $actor): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $limitOverrides, $moduleOverrides, $actor) {
            $subscription = Subscription::findOrFail($subscriptionId);

            $subscription->update([
                'limit_overrides'  => $limitOverrides,
                'module_overrides' => $moduleOverrides,
            ]);

            AuditLogger::subscriptionCapabilitiesModified($actor, $subscription, $limitOverrides, $moduleOverrides);

            return $subscription->fresh();
        });
    }

    /**
     * Self-service plan change for an authenticated tenant.
     *
     * Validates the transition, cleans up redundant module overrides,
     * updates the subscription, and logs the change.
     *
     * BILLING NOTE: Stripe/payment hooks are stubbed with clear comment markers.
     * When billing is wired up, dispatch a PlanChangedEvent here.
     *
     * @param int   $businessId   The tenant's business ID
     * @param int   $targetPlanId The plan being switched to
     * @param mixed $actor        The authenticated tenant user
     *
     * @return Subscription
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Exception  Transition validation failure (e.g., downgrade blocked)
     */
    public function changePlan(int $businessId, int $targetPlanId, mixed $actor): Subscription
    {
        return DB::transaction(function () use ($businessId, $targetPlanId, $actor) {
            $subscription = Subscription::where('business_id', $businessId)->firstOrFail();
            $targetPlan   = Plan::findOrFail($targetPlanId);

            // ── Phase 1: Pre-flight validation (downgrade rules, etc.) ─────────
            $this->planTransitionValidator->execute($businessId, $targetPlanId, $subscription);

            // ── Phase 2: Override lifecycle — clean redundant module overrides ──
            $moduleOverrides   = $subscription->module_overrides ?? [];
            $targetBaseModules = $targetPlan->enabled_modules ?? [];
            if (is_string($targetBaseModules)) {
                $targetBaseModules = json_decode($targetBaseModules, true) ?? [];
            }

            if (isset($moduleOverrides['added'])) {
                $moduleOverrides['added'] = array_values(
                    array_diff($moduleOverrides['added'], $targetBaseModules)
                );
            }
            if (isset($moduleOverrides['removed'])) {
                $moduleOverrides['removed'] = array_values(
                    array_intersect($moduleOverrides['removed'], $targetBaseModules)
                );
            }

            // ── Phase 3: Financial hook stub ──────────────────────────────────
            $oldPlanId = $subscription->plan_id;
            $oldPlan   = Plan::find($oldPlanId);
            // $isUpgrade = $oldPlan && $targetPlan->price > $oldPlan->price;
            // TODO: Event::dispatch(new PlanChangedEvent($subscription, $targetPlan, $isUpgrade));
            // TODO: If upgrade + upfront billing: set status = 'pending_payment'

            // ── Phase 4: Persist ──────────────────────────────────────────────
            $subscription->update([
                'plan_id'          => $targetPlan->id,
                'module_overrides' => $moduleOverrides,
                'status'           => 'active',
            ]);

            AuditLogger::tenantPlanChanged($actor, $subscription, $oldPlanId, $targetPlan->id, $targetPlan->name);

            return $subscription->fresh();
        });
    }
}
