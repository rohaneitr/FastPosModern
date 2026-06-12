<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use Illuminate\Support\Collection;

/**
 * PlanManagementService
 *
 * Extracted from SubscriptionController (lines 19–135):
 *   - getPlans()    — list with legacy column normalization
 *   - storePlan()   — create new plan
 *   - updatePlan()  — update existing plan
 *   - destroyPlan() — safe delete with active subscription guard
 *
 * WHY A SERVICE?
 * Plan management is a domain concern, not HTTP logic.
 * Allows reuse from CLI commands (e.g., seeding, imports) and
 * removes duplication in storePlan/updatePlan (identical validation + mapping).
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.3
 * @version 2026-06-12
 */
class PlanManagementService
{
    /**
     * Retrieve all plans with legacy column normalization.
     */
    public function list(): Collection
    {
        return Plan::all()->map(function (Plan $plan) {
            // Normalize legacy column names for frontend backward-compatibility
            if (!isset($plan->employee_limit) && isset($plan->max_users)) {
                $plan->employee_limit = $plan->max_users;
            }
            if (!isset($plan->device_limit)) {
                $plan->device_limit = 1;
            }
            return $plan;
        });
    }

    /**
     * Build the normalized data array from validated input.
     * Used by both create and update — single source of truth.
     *
     * @param array $validated
     * @return array
     */
    private function buildPlanData(array $validated): array
    {
        $data = [
            'name'            => $validated['name'],
            'price'           => $validated['price'],
            'interval'        => $validated['interval'],
            'user_limit'      => $validated['user_limit']     ?? $validated['max_users']     ?? 1,
            'location_limit'  => $validated['location_limit'] ?? $validated['max_locations'] ?? 1,
            'device_limit'    => $validated['device_limit']   ?? 1,
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'plan_type'       => $validated['plan_type']       ?? null,
        ];

        if (isset($validated['enabled_modules'])) {
            $data['enabled_modules'] = json_encode($validated['enabled_modules']);
        }

        return $data;
    }

    /**
     * Create a new plan.
     *
     * @param array $validated  Already-validated input from Controller
     * @return Plan
     */
    public function create(array $validated): Plan
    {
        return Plan::create($this->buildPlanData($validated));
    }

    /**
     * Update an existing plan.
     *
     * @param int   $id
     * @param array $validated
     * @return Plan
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(int $id, array $validated): Plan
    {
        $plan = Plan::findOrFail($id);
        $plan->update($this->buildPlanData($validated));
        return $plan->fresh();
    }

    /**
     * Delete a plan, with an active-subscription guard.
     *
     * @param int $id
     * @throws \RuntimeException   If active subscriptions exist on this plan
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(int $id): void
    {
        $plan = Plan::findOrFail($id);

        $hasActive = Subscription::where('plan_id', $id)
            ->where('status', 'active')
            ->exists();

        if ($hasActive) {
            throw new \RuntimeException(
                'Cannot delete plan with active subscriptions. Migrate tenants to another plan first.'
            );
        }

        $plan->delete();
    }
}
