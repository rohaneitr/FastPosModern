<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use App\Modules\Tenant\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * SuperAdmin: Get all available plans
     */
    public function getPlans()
    {
        $plans = Plan::all()->map(function ($plan) {
            // Map legacy columns if needed
            if (!isset($plan->employee_limit) && isset($plan->max_users)) {
                $plan->employee_limit = $plan->max_users;
            }
            if (!isset($plan->device_limit)) {
                $plan->device_limit = 1; // Default
            }
            return $plan;
        });
        return response()->json($plans);
    }

    /**
     * SuperAdmin: Get all system modules
     */
    public function getSystemModules()
    {
        $modules = DB::table('modules')->get();
        return response()->json($modules);
    }

    /**
     * SuperAdmin: Create a new plan
     */
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'max_users' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'max_locations' => 'nullable|integer|min:1',
            'location_limit' => 'nullable|integer|min:1',
            'device_limit' => 'nullable|integer|min:1',
            'stripe_price_id' => 'nullable|string',
            'plan_type' => 'nullable|string',
            'enabled_modules' => 'nullable|array'
        ]);

        $data = [
            'name' => $validated['name'],
            'price' => $validated['price'],
            'interval' => $validated['interval'],
            'user_limit' => $validated['user_limit'] ?? $validated['max_users'] ?? 1,
            'location_limit' => $validated['location_limit'] ?? $validated['max_locations'] ?? 1,
            'device_limit' => $validated['device_limit'] ?? 1,
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'plan_type' => $validated['plan_type'] ?? null,
        ];

        if (isset($validated['enabled_modules'])) {
            $data['enabled_modules'] = json_encode($validated['enabled_modules']);
        }

        $plan = Plan::create($data);
        return response()->json(['message' => 'Plan created', 'plan' => $plan], 201);
    }

    /**
     * SuperAdmin: Update an existing plan
     */
    public function updatePlan(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'max_users' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'max_locations' => 'nullable|integer|min:1',
            'location_limit' => 'nullable|integer|min:1',
            'device_limit' => 'nullable|integer|min:1',
            'stripe_price_id' => 'nullable|string',
            'plan_type' => 'nullable|string',
            'enabled_modules' => 'nullable|array'
        ]);

        $data = [
            'name' => $validated['name'],
            'price' => $validated['price'],
            'interval' => $validated['interval'],
            'user_limit' => $validated['user_limit'] ?? $validated['max_users'] ?? 1,
            'location_limit' => $validated['location_limit'] ?? $validated['max_locations'] ?? 1,
            'device_limit' => $validated['device_limit'] ?? 1,
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'plan_type' => $validated['plan_type'] ?? null,
        ];

        if (isset($validated['enabled_modules'])) {
            $data['enabled_modules'] = json_encode($validated['enabled_modules']);
        }

        $plan->update($data);
        return response()->json(['message' => 'Plan updated successfully', 'plan' => $plan]);
    }

    /**
     * SuperAdmin: Delete a plan
     */
    public function destroyPlan($id)
    {
        $plan = Plan::findOrFail($id);

        $hasActiveSubscriptions = Subscription::where('plan_id', $id)->where('status', 'active')->exists();
        if ($hasActiveSubscriptions) {
            return response()->json(['message' => 'Cannot delete plan with active subscriptions. Soft-delete instead or migrate tenants.'], 400);
        }

        $plan->delete(); // Assuming SoftDeletes is on Plan model, else it hard deletes
        return response()->json(['message' => 'Plan deleted successfully']);
    }

    /**
     * SuperAdmin: Renew or extend a subscription
     */
    public function renew(Request $request, $id)
    {
        $request->validate([
            'extension_period' => 'required|in:1_month,1_year'
        ]);

        return DB::transaction(function() use ($request, $id) {
            $subscription = Subscription::with('business')->findOrFail($id);
            
            $oldEnd = $subscription->current_period_end ? Carbon::parse($subscription->current_period_end) : now();
            $currentEnd = $oldEnd->copy();
            
            // If expired, start from today
            if ($currentEnd->isPast()) {
                $currentEnd = now();
            }

            if ($request->extension_period === '1_month') {
                $newEnd = $currentEnd->addMonth();
            } else {
                $newEnd = $currentEnd->addYear();
            }

            $subscription->update([
                'current_period_end' => $newEnd,
                'status' => 'active'
            ]);

            // Reactivate business if it was suspended due to expiration
            if ($subscription->business) {
                $subscription->business->update([
                    'is_active' => true,
                    'subscription_ends_at' => $newEnd->format('Y-m-d'),
                    'subscription_status' => 'Active'
                ]);
            }

            \App\Modules\Tenant\Services\AuditLogger::subscriptionRenewed(
                $request->user(), 
                $subscription, 
                $oldEnd->toDateTimeString(), 
                $newEnd->toDateTimeString(), 
                $request->extension_period
            );

            return response()->json([
                'message' => 'Subscription renewed successfully',
                'subscription' => $subscription
            ]);
        });
    }

    /**
     * SuperAdmin: Override subscription status manually
     */
    public function overrideStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,canceled,past_due'
        ]);

        return DB::transaction(function() use ($request, $id) {
            $subscription = Subscription::with('business')->findOrFail($id);
            
            $oldStatus = $subscription->status;
            $newStatus = $request->status;
            
            $subscription->update(['status' => $newStatus]);
            
            // Ensure downstream business state is accurately reflected to restrict login
            if ($subscription->business) {
                if (in_array($newStatus, ['suspended', 'canceled', 'past_due'])) {
                    $subscription->business->update([
                        'is_active' => false,
                        'subscription_status' => ucfirst($newStatus)
                    ]);
                } else if ($newStatus === 'active') {
                    $subscription->business->update([
                        'is_active' => true,
                        'subscription_status' => 'Active'
                    ]);
                }
            }

            \App\Modules\Tenant\Services\AuditLogger::subscriptionStatusOverridden(
                $request->user(),
                $subscription,
                $oldStatus,
                $newStatus
            );

            return response()->json([
                'message' => 'Subscription status overridden successfully',
                'subscription' => $subscription
            ]);
        });
    }

    /**
     * SuperAdmin: Override capabilities per tenant
     */
    public function updateCapabilities(Request $request, $id)
    {
        $request->validate([
            'limit_overrides' => 'nullable|array',
            'module_overrides' => 'nullable|array',
            'module_overrides.added' => 'nullable|array',
            'module_overrides.removed' => 'nullable|array'
        ]);

        return DB::transaction(function() use ($request, $id) {
            $subscription = Subscription::findOrFail($id);
            
            $limitOverrides = $request->limit_overrides ?? [];
            $moduleOverrides = $request->module_overrides ?? [];
            
            $subscription->update([
                'limit_overrides' => $limitOverrides,
                'module_overrides' => $moduleOverrides
            ]);

            \App\Modules\Tenant\Services\AuditLogger::subscriptionCapabilitiesModified(
                $request->user(),
                $subscription,
                $limitOverrides,
                $moduleOverrides
            );

            return response()->json([
                'message' => 'Capability overrides updated successfully',
                'subscription' => $subscription
            ]);
        });
    }

    /**
     * Tenant: Change Plan (Self-Service)
     */
    public function changePlan(Request $request, \App\Modules\Tenant\Actions\ValidatePlanTransitionAction $validator)
    {
        $request->validate(['target_plan_id' => 'required|exists:plans,id']);
        
        $businessId = $request->user()->business_id;
        
        return DB::transaction(function() use ($request, $validator, $businessId) {
            $subscription = Subscription::where('business_id', $businessId)->firstOrFail();
            $targetPlan = Plan::findOrFail($request->target_plan_id);
            
            // Phase 1: Pre-flight Validation
            $validator->execute($businessId, $targetPlan->id, $subscription);
            
            // Phase 2: Override Lifecycle Policy
            $moduleOverrides = $subscription->module_overrides ?? [];
            
            // Check native modules of new plan
            $targetBaseModules = $targetPlan->enabled_modules ?? [];
            if (is_string($targetBaseModules)) {
                $targetBaseModules = json_decode($targetBaseModules, true) ?? [];
            }
            
            // Clean module overrides if redundant
            if (isset($moduleOverrides['added'])) {
                $moduleOverrides['added'] = array_values(array_diff($moduleOverrides['added'], $targetBaseModules));
            }
            if (isset($moduleOverrides['removed'])) {
                $moduleOverrides['removed'] = array_values(array_intersect($moduleOverrides['removed'], $targetBaseModules));
            }
            
            $oldPlanId = $subscription->plan_id;
            $oldPlan = Plan::find($oldPlanId);
            
            // Phase 3: Financial Synchronization Hooks (Billing)
            // Determine if this is an upgrade, downgrade, or lateral move
            $isUpgrade = $oldPlan && $targetPlan->price > $oldPlan->price;
            $status = 'active';

            // IMPORTANT: Insert Payment Gateway Logic Here.
            // If the system requires upfront payment for an upgrade, we set the status to 'pending_payment'.
            // Event::dispatch(new PlanChangedEvent($subscription, $targetPlan, $isUpgrade));
            if ($isUpgrade) {
                // $status = 'pending_payment';
                // Trigger Stripe/Local Wallet invoice generation
            }
            
            // Update the subscription
            // Quantitative Overrides (limit_overrides) MUST be preserved implicitly
            $subscription->update([
                'plan_id' => $targetPlan->id,
                'module_overrides' => $moduleOverrides,
                'status' => $status
                // 'current_period_end' => ... Calculate prorated expiration if necessary
            ]);
            
            // Log the action
            \App\Modules\Tenant\Services\AuditLogger::tenantPlanChanged(
                $request->user(),
                $subscription,
                $oldPlanId,
                $targetPlan->id,
                $targetPlan->name
            );
            
            return response()->json([
                'message' => 'Plan changed successfully',
                'subscription' => $subscription->fresh()
            ]);
        });
    }

    /**
     * Tenant: Get current subscription status
     */
    public function currentSubscription(Request $request)
    {
        $business = Business::with('subscription.plan')->findOrFail($request->user()->business_id);
        
        return response()->json([
            'subscription' => $business->subscription,
            'is_active' => $business->subscription ? $business->subscription->isActive() : false,
            'is_past_due' => $business->subscription ? $business->subscription->isPastDue() : false,
        ]);
    }

    /**
     * Tenant: Subscribe to a plan.
     * Supports both Stripe (when STRIPE_SECRET is configured) and mock mode.
     */
    public function subscribe(Request $request)
    {
        $business = Business::findOrFail($request->user()->business_id);
        
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_token' => 'required|string'
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $stripeEnabled = !empty(config('services.stripe.secret'));

        if ($stripeEnabled && $plan->stripe_price_id) {
            return $this->subscribeViaStripe($business, $plan, $validated['payment_token']);
        }

        return $this->subscribeMock($business, $plan);
    }

    /**
     * Subscribe via Stripe API.
     */
    private function subscribeViaStripe(Business $business, Plan $plan, string $paymentToken)
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Create or retrieve Stripe customer
            if (!$business->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'name' => $business->name,
                    'metadata' => ['business_id' => $business->id],
                ]);
                $business->update(['stripe_customer_id' => $customer->id]);
            }

            // Attach payment method
            $pm = \Stripe\PaymentMethod::retrieve($paymentToken);
            $pm->attach(['customer' => $business->stripe_customer_id]);

            // Set as default payment method
            \Stripe\Customer::update($business->stripe_customer_id, [
                'invoice_settings' => ['default_payment_method' => $paymentToken],
            ]);

            // Create subscription
            $stripeSubscription = \Stripe\Subscription::create([
                'customer' => $business->stripe_customer_id,
                'items' => [['price' => $plan->stripe_price_id]],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            // Store locally
            $sub = Subscription::updateOrCreate(
                ['business_id' => $business->id],
                [
                    'plan_id' => $plan->id,
                    'status' => $stripeSubscription->status === 'active' ? 'active' : 'pending',
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                    'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                ]
            );

            return response()->json([
                'message' => 'Subscribed via Stripe',
                'subscription' => $sub,
                'client_secret' => $stripeSubscription->latest_invoice->payment_intent->client_secret ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe subscription failed: ' . $e->getMessage());
            return response()->json(['message' => 'Payment failed', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Mock subscription (no Stripe configured).
     */
    private function subscribeMock(Business $business, Plan $plan)
    {
        $sub = Subscription::updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $plan->interval === 'month' ? now()->addMonth() : now()->addYear(),
            ]
        );

        return response()->json(['message' => 'Subscribed successfully (mock)', 'subscription' => $sub]);
    }

    /**
     * Stripe Webhook handler.
     * Register this route as public (no auth) at POST /api/v1/webhooks/stripe
     */
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
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

        switch ($type) {
            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($data);
                break;
            case 'invoice.payment_failed':
                $this->handlePaymentFailed($data);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancelled($data);
                break;
        }

        return response()->json(['received' => true]);
    }

    private function handlePaymentSucceeded($invoice)
    {
        $subId = $invoice->subscription ?? ($invoice['subscription'] ?? null);
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update([
            'status' => 'active',
        ]);

        Log::info("Stripe: Payment succeeded for subscription {$subId}");
    }

    private function handlePaymentFailed($invoice)
    {
        $subId = $invoice->subscription ?? ($invoice['subscription'] ?? null);
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update([
            'status' => 'past_due',
        ]);

        Log::warning("Stripe: Payment failed for subscription {$subId}");
    }

    private function handleSubscriptionCancelled($subscription)
    {
        $subId = $subscription->id ?? ($subscription['id'] ?? null);
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update([
            'status' => 'cancelled',
        ]);

        Log::info("Stripe: Subscription cancelled {$subId}");
    }

    /**
     * Customer billing portal redirect URL (Stripe-only).
     */
    public function billingPortal(Request $request)
    {
        $business = Business::findOrFail($request->user()->business_id);

        if (!$business->stripe_customer_id) {
            return response()->json(['message' => 'No Stripe customer found. Subscribe first.'], 422);
        }

        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $business->stripe_customer_id,
                'return_url' => config('app.frontend_url', 'http://localhost:3000') . '/business/settings',
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create portal session', 'error' => $e->getMessage()], 500);
        }
    }
}
