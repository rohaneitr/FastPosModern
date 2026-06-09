<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use App\Modules\Tenant\Models\Business;
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
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'max_users' => 'required|integer|min:1',
            'max_locations' => 'required|integer|min:1',
            'stripe_price_id' => 'nullable|string'
        ]);

        $plan = Plan::create($validated);
        return response()->json(['message' => 'Plan created', 'plan' => $plan], 201);
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
