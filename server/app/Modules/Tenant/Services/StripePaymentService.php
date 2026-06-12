<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * StripePaymentService
 *
 * Extracted from SubscriptionController (lines 390–558):
 *   - subscribeViaStripe()       — Stripe customer + payment method + subscription
 *   - handlePaymentSucceeded()   — Webhook: invoice.payment_succeeded
 *   - handlePaymentFailed()      — Webhook: invoice.payment_failed
 *   - handleSubscriptionCancelled() — Webhook: customer.subscription.deleted
 *   - createBillingPortalSession() — Stripe Billing Portal URL generation
 *
 * STRIPE WEBHOOK SECURITY:
 *   - Signature verification is handled in the controller BEFORE calling this service
 *   - This service only receives the already-verified event payload
 *
 * ZERO TRUST:
 *   - Stripe API key is read from config() — never hardcoded
 *   - Stripe customer ID is stored in businesses table, never in a cookie
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.3
 * @version 2026-06-12
 */
class StripePaymentService
{
    /**
     * Subscribe a business to a plan via Stripe.
     *
     * @param Business $business
     * @param Plan     $plan         Must have stripe_price_id set
     * @param string   $paymentToken Stripe PaymentMethod ID from frontend
     *
     * @return array{subscription: Subscription, client_secret: ?string}
     *
     * @throws \Exception  Any Stripe API exception
     */
    public function subscribe(Business $business, Plan $plan, string $paymentToken): array
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // ── 1. Create or reuse Stripe Customer ───────────────────────────────
        if (!$business->stripe_customer_id) {
            $customer = \Stripe\Customer::create([
                'name'     => $business->name,
                'metadata' => ['business_id' => $business->id],
            ]);
            $business->update(['stripe_customer_id' => $customer->id]);
        }

        // ── 2. Attach payment method ─────────────────────────────────────────
        $pm = \Stripe\PaymentMethod::retrieve($paymentToken);
        $pm->attach(['customer' => $business->stripe_customer_id]);

        // ── 3. Set as default payment method ─────────────────────────────────
        \Stripe\Customer::update($business->stripe_customer_id, [
            'invoice_settings' => ['default_payment_method' => $paymentToken],
        ]);

        // ── 4. Create Stripe Subscription ────────────────────────────────────
        $stripeSubscription = \Stripe\Subscription::create([
            'customer' => $business->stripe_customer_id,
            'items'    => [['price' => $plan->stripe_price_id]],
            'expand'   => ['latest_invoice.payment_intent'],
        ]);

        // ── 5. Persist locally ────────────────────────────────────────────────
        $sub = Subscription::updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan_id'                => $plan->id,
                'status'                 => $stripeSubscription->status === 'active' ? 'active' : 'pending',
                'stripe_subscription_id' => $stripeSubscription->id,
                'current_period_start'   => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end'     => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]
        );

        return [
            'subscription'  => $sub,
            'client_secret' => $stripeSubscription->latest_invoice?->payment_intent?->client_secret,
        ];
    }

    /**
     * Handle Stripe invoice.payment_succeeded webhook.
     *
     * @param object|array $invoice  Already-verified Stripe invoice object
     */
    public function handlePaymentSucceeded(mixed $invoice): void
    {
        $subId = is_object($invoice) ? ($invoice->subscription ?? null) : ($invoice['subscription'] ?? null);
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update(['status' => 'active']);
        Log::info("Stripe: Payment succeeded for subscription {$subId}");
    }

    /**
     * Handle Stripe invoice.payment_failed webhook.
     *
     * @param object|array $invoice
     */
    public function handlePaymentFailed(mixed $invoice): void
    {
        $subId = is_object($invoice) ? ($invoice->subscription ?? null) : ($invoice['subscription'] ?? null);
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update(['status' => 'past_due']);
        Log::warning("Stripe: Payment failed for subscription {$subId}");
    }

    /**
     * Handle Stripe customer.subscription.deleted webhook.
     *
     * @param object|array $subscription
     */
    public function handleSubscriptionCancelled(mixed $subscription): void
    {
        $subId = is_object($subscription) ? ($subscription->id ?? null) : ($subscription['id'] ?? null);
        if (!$subId) return;

        Subscription::where('stripe_subscription_id', $subId)->update(['status' => 'cancelled']);
        Log::info("Stripe: Subscription cancelled {$subId}");
    }

    /**
     * Generate a Stripe Billing Portal session URL for self-service billing management.
     *
     * @param Business $business  Must have stripe_customer_id
     * @return string   The Stripe-hosted portal URL
     *
     * @throws \RuntimeException  If Stripe customer not found
     * @throws \Exception         Any Stripe API exception
     */
    public function createBillingPortalSession(Business $business): string
    {
        if (!$business->stripe_customer_id) {
            throw new \RuntimeException('No Stripe customer found. Subscribe first.');
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $session = \Stripe\BillingPortal\Session::create([
            'customer'   => $business->stripe_customer_id,
            'return_url' => config('app.frontend_url', 'http://localhost:3000') . '/business/settings',
        ]);

        return $session->url;
    }

    /**
     * Mock subscription fallback (no Stripe configured).
     *
     * @param Business $business
     * @param Plan     $plan
     * @return Subscription
     */
    public function subscribeMock(Business $business, Plan $plan): Subscription
    {
        return Subscription::updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan_id'              => $plan->id,
                'status'               => 'active',
                'current_period_start' => now(),
                'current_period_end'   => $plan->interval === 'month' ? now()->addMonth() : now()->addYear(),
            ]
        );
    }
}
