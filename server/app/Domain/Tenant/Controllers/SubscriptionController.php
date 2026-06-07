<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Tenant\Models\Plan;
use App\Domain\Tenant\Models\Subscription;
use App\Domain\Tenant\Models\SubscriptionRequest;
use App\Domain\Tenant\Models\Business;
use App\Domain\Tenant\Actions\ProvisionSubscriptionAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * SuperAdmin: Get all available plans
     */
    public function getPlans()
    {
        $plans = Plan::all();
        return response()->json($plans);
    }

    /**
     * SuperAdmin: Create a new plan
     */
    public function storePlan(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'interval' => 'nullable|string',
            'max_users' => 'nullable|integer',
            'max_locations' => 'nullable|integer',
            'plan_type' => 'nullable|string',
            'plan_architecture' => 'nullable|string',
            'device_limit' => 'nullable|integer',
            'employee_limit' => 'nullable|integer',
            'enabled_modules' => 'nullable|array',
            'features' => 'nullable|array',
        ]);

        try {
            // Disable all Observers/Events temporarily to prevent background crashes
            $plan = Plan::withoutEvents(function () use ($validated, $request) {
                $newPlan = new Plan();
                $newPlan->name = $validated['name'];
                $newPlan->price = $validated['price'];
                $newPlan->interval = $validated['interval'] ?? 'month';
                $newPlan->max_users = $validated['max_users'] ?? $validated['employee_limit'] ?? 1;
                $newPlan->max_locations = $validated['max_locations'] ?? 1;
                $newPlan->plan_type = $validated['plan_type'] ?? $validated['plan_architecture'] ?? 'online_web';
                $newPlan->device_limit = $validated['device_limit'] ?? 1;
                $newPlan->employee_limit = $validated['employee_limit'] ?? 1;
                
                // Force clean array explicitly, preventing any object serialization
                $newPlan->enabled_modules = is_array($request->enabled_modules) ? $request->enabled_modules : [];
                $newPlan->features = is_array($request->features) ? $request->features : [];
                $newPlan->save();

                return $newPlan;
            });

            return response()->json(['message' => 'Plan created safely', 'plan' => $plan], 201);

        } catch (\Throwable $e) {
            // The TRAP: If it still crashes, log the exact file and stack trace
            \Illuminate\Support\Facades\Log::emergency('PLAN_CRASH_TRACE', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'System Error: ' . $e->getMessage()], 500);
        }
    }

    public function updatePlan(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'price' => 'sometimes|required|numeric|min:0',
            'interval' => 'nullable|in:month,year',
            'max_users' => 'nullable|integer|min:1',
            'max_locations' => 'nullable|integer|min:1',
            'plan_type' => 'sometimes|required|string|in:online_web,hybrid_offline_sync,mobile_native',
            'device_limit' => 'sometimes|required|integer|min:1',
            'employee_limit' => 'sometimes|required|integer|min:1',
            'stripe_price_id' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => 'string'
        ]);

        if (isset($validated['employee_limit']) && !isset($validated['max_users'])) {
            $validated['max_users'] = $validated['employee_limit'];
        }

        $planData = $validated;
        $planData['enabled_modules'] = $request->enabled_modules ?? [];

        $plan->update($planData);
        return response()->json(['message' => 'Plan updated successfully', 'plan' => $plan]);
    }

    public function destroyPlan($id)
    {
        $plan = Plan::findOrFail($id);
        
        $activeSubscriptions = Subscription::where('plan_id', $id)->where('status', 'active')->count();
        if ($activeSubscriptions > 0) {
            return response()->json(['message' => 'Cannot delete plan because active tenants are subscribed to it.'], 422);
        }

        $plan->delete();
        return response()->json(['message' => 'Plan deleted successfully']);
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
            $event = json_decode($payload, true);
        }

        $type = is_object($event) && isset($event->type) ? $event->type : ($event['type'] ?? null);
        
        $data = [];
        if (is_object($event) && isset($event->data)) {
            $data = is_object($event->data->object) ? (array) $event->data->object : $event->data->object;
        } elseif (is_array($event) && isset($event['data']['object'])) {
            $data = $event['data']['object'];
        }

        \App\Jobs\ProcessStripeWebhookJob::dispatch($type, (array)$data);

        return response()->json(['received' => true]);
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

    public function manualAssign(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        $request->validate([
            'tenant_id' => 'required|exists:businesses,id',
            'plan_id' => 'required|exists:plans,id',
            'custom_expiry_date' => 'nullable|date'
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $customExpiryDate = $request->custom_expiry_date ? Carbon::parse($request->custom_expiry_date) : null;

        $provisionAction = new ProvisionSubscriptionAction();
        $result = $provisionAction->execute($request->tenant_id, $plan, $customExpiryDate);

        return response()->json([
            'message' => 'Subscription manually assigned',
            'subscription' => $result['subscription'],
            'license_key' => $result['license_key']
        ]);
    }

    /**
     * Tenant: Request a subscription (Offline / Manual Approval)
     */
    public function requestSubscription(Request $request)
    {
        $business = Business::findOrFail($request->user()->business_id);
        
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'required|string',
            'transaction_reference' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        // Check if there is already a pending request for this business
        $existing = SubscriptionRequest::where('business_id', $business->id)
                                       ->where('status', 'pending')
                                       ->first();
        if ($existing) {
            return response()->json(['message' => 'You already have a pending subscription request. Please wait for approval.'], 422);
        }

        $subReq = SubscriptionRequest::create([
            'business_id' => $business->id,
            'plan_id' => $validated['plan_id'],
            'status' => 'pending',
            'payment_method' => $validated['payment_method'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Subscription request submitted successfully', 'request' => $subReq], 201);
    }

    /**
     * SuperAdmin: Get Subscription Requests
     */
    public function getSubscriptionRequests(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }

        $query = SubscriptionRequest::with(['business', 'plan', 'reviewer'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * SuperAdmin: Approve Subscription Request
     */
    public function approveSubscriptionRequest(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }

        $subReq = SubscriptionRequest::findOrFail($id);

        if ($subReq->status !== 'pending') {
            return response()->json(['message' => 'Request is already ' . $subReq->status], 422);
        }

        $plan = Plan::findOrFail($subReq->plan_id);

        // Provision the subscription
        $provisionAction = new ProvisionSubscriptionAction();
        $result = $provisionAction->execute($subReq->business_id, $plan);

        // Update request status
        $subReq->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Subscription request approved successfully',
            'subscription' => $result['subscription']
        ]);
    }

    /**
     * SuperAdmin: Reject Subscription Request
     */
    public function rejectSubscriptionRequest(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }

        $subReq = SubscriptionRequest::findOrFail($id);

        if ($subReq->status !== 'pending') {
            return response()->json(['message' => 'Request is already ' . $subReq->status], 422);
        }

        $subReq->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'notes' => $request->notes ?? $subReq->notes
        ]);

        return response()->json(['message' => 'Subscription request rejected successfully']);
    }
}
