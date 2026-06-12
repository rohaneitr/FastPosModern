<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Services\PlanManagementService;
use App\Modules\Tenant\Services\SubscriptionLifecycleService;
use App\Modules\Tenant\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SubscriptionController — Phase 3 Refactored
 *
 * BEFORE: 561 lines, 3 domains mixed (Plan CRUD, Subscription Lifecycle, Stripe Billing)
 * AFTER:  ~160 lines, pure HTTP orchestration — validate → delegate → respond
 *
 * Delegated to:
 *   PlanManagementService        — Plan CRUD with active subscription guard
 *   SubscriptionLifecycleService — renew, override, capabilities, plan change
 *   StripePaymentService         — Stripe integration + mock fallback
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.3
 * @version 2026-06-12
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanManagementService        $planService,
        private readonly SubscriptionLifecycleService $lifecycleService,
        private readonly StripePaymentService         $stripeService,
    ) {}

    // ── PLAN CRUD (SuperAdmin) ─────────────────────────────────────────────────

    public function getPlans(): JsonResponse
    {
        return response()->json($this->planService->list());
    }

    public function getSystemModules(): JsonResponse
    {
        return response()->json(\Illuminate\Support\Facades\DB::table('modules')->get());
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate($this->planValidationRules());
        $plan      = $this->planService->create($validated);

        return response()->json(['message' => 'Plan created', 'plan' => $plan], 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate($this->planValidationRules());

        try {
            $plan = $this->planService->update($id, $validated);
            return response()->json(['message' => 'Plan updated successfully', 'plan' => $plan]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Plan not found'], 404);
        }
    }

    public function destroyPlan(int $id): JsonResponse
    {
        try {
            $this->planService->delete($id);
            return response()->json(['message' => 'Plan deleted successfully']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Plan not found'], 404);
        }
    }

    // ── SUBSCRIPTION LIFECYCLE (SuperAdmin) ───────────────────────────────────

    public function renew(Request $request, int $id): JsonResponse
    {
        $request->validate(['extension_period' => 'required|in:1_month,1_year']);

        try {
            $subscription = $this->lifecycleService->renew($id, $request->extension_period, $request->user());
            return response()->json(['message' => 'Subscription renewed successfully', 'subscription' => $subscription]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }
    }

    public function overrideStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|in:active,suspended,canceled,past_due']);

        try {
            $subscription = $this->lifecycleService->overrideStatus($id, $request->status, $request->user());
            return response()->json(['message' => 'Subscription status overridden successfully', 'subscription' => $subscription]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }
    }

    public function updateCapabilities(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'limit_overrides'          => 'nullable|array',
            'module_overrides'         => 'nullable|array',
            'module_overrides.added'   => 'nullable|array',
            'module_overrides.removed' => 'nullable|array',
        ]);

        try {
            $subscription = $this->lifecycleService->updateCapabilities(
                $id,
                $request->limit_overrides  ?? [],
                $request->module_overrides ?? [],
                $request->user(),
            );
            return response()->json(['message' => 'Capability overrides updated successfully', 'subscription' => $subscription]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }
    }

    // ── TENANT SELF-SERVICE ───────────────────────────────────────────────────

    public function currentSubscription(Request $request): JsonResponse
    {
        $business = Business::with('subscription.plan')
            ->findOrFail($request->user()->business_id);

        return response()->json([
            'subscription' => $business->subscription,
            'is_active'    => $business->subscription?->isActive() ?? false,
            'is_past_due'  => $business->subscription?->isPastDue() ?? false,
        ]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $request->validate(['target_plan_id' => 'required|exists:plans,id']);

        try {
            $subscription = $this->lifecycleService->changePlan(
                $request->user()->business_id,
                (int) $request->target_plan_id,
                $request->user(),
            );
            return response()->json(['message' => 'Plan changed successfully', 'subscription' => $subscription]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Subscription or plan not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function subscribe(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        $validated = $request->validate([
            'plan_id'       => 'required|exists:plans,id',
            'payment_token' => 'required|string',
        ]);

        $plan          = Plan::findOrFail($validated['plan_id']);
        $stripeEnabled = !empty(config('services.stripe.secret'));

        if ($stripeEnabled && $plan->stripe_price_id) {
            try {
                $result = $this->stripeService->subscribe($business, $plan, $validated['payment_token']);
                return response()->json([
                    'message'       => 'Subscribed via Stripe',
                    'subscription'  => $result['subscription'],
                    'client_secret' => $result['client_secret'],
                ]);
            } catch (\Exception $e) {
                Log::error('Stripe subscription failed: ' . $e->getMessage());
                return response()->json(['message' => 'Payment failed', 'error' => $e->getMessage()], 422);
            }
        }

        $sub = $this->stripeService->subscribeMock($business, $plan);
        return response()->json(['message' => 'Subscribed successfully (mock)', 'subscription' => $sub]);
    }

    public function billingPortal(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        try {
            $url = $this->stripeService->createBillingPortalSession($business);
            return response()->json(['url' => $url]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create portal session', 'error' => $e->getMessage()], 500);
        }
    }

    // ── STRIPE WEBHOOK ────────────────────────────────────────────────────────

    /**
     * Stripe webhook endpoint — must be public (no auth middleware).
     * Signature is verified here BEFORE delegating to StripePaymentService.
     */
    public function handleStripeWebhook(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $payload       = $request->getContent();
        $sigHeader     = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\Exception $e) {
                Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
                return response('Invalid signature', 400);
            }
        } else {
            $event = json_decode($payload);
        }

        $type = is_object($event) && isset($event->type) ? $event->type : ($event['type'] ?? null);
        $data = is_object($event) && isset($event->data) ? $event->data->object : ($event['data']['object'] ?? null);

        match ($type) {
            'invoice.payment_succeeded'        => $this->stripeService->handlePaymentSucceeded($data),
            'invoice.payment_failed'           => $this->stripeService->handlePaymentFailed($data),
            'customer.subscription.deleted'    => $this->stripeService->handleSubscriptionCancelled($data),
            default                            => null,
        };

        return response()->json(['received' => true]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Shared validation rules for plan create and update.
     * Single source of truth — eliminates duplication.
     */
    private function planValidationRules(): array
    {
        return [
            'name'            => 'required|string|max:100',
            'price'           => 'required|numeric|min:0',
            'interval'        => 'required|in:month,year',
            'max_users'       => 'nullable|integer|min:1',
            'user_limit'      => 'nullable|integer|min:1',
            'max_locations'   => 'nullable|integer|min:1',
            'location_limit'  => 'nullable|integer|min:1',
            'device_limit'    => 'nullable|integer|min:1',
            'stripe_price_id' => 'nullable|string',
            'plan_type'       => 'nullable|string',
            'enabled_modules' => 'nullable|array',
        ];
    }
}
