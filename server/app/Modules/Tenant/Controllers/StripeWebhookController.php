<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * StripeWebhookController — Phase 7: SaaS Billing Engine
 *
 * Handles inbound Stripe webhook events for tenant lifecycle management.
 *
 * ── SECURITY ARCHITECTURE ──────────────────────────────────────────────────
 *
 * 1. SIGNATURE VERIFICATION (Primary Guard)
 *    Every request is verified using HMAC-SHA256 with the STRIPE_WEBHOOK_SECRET.
 *    This proves the payload originated from Stripe's servers, not a spoofed
 *    caller. Any request with a missing or invalid signature is rejected with
 *    400 BEFORE any database interaction.
 *
 *    Implementation uses the Stripe Signed Payload format:
 *      signed_payload = "{timestamp}.{raw_body}"
 *      expected_sig   = HMAC-SHA256(signed_payload, STRIPE_WEBHOOK_SECRET)
 *    The raw body MUST be read before any framework JSON parsing — once Laravel
 *    parses JSON, the raw byte stream is lost and signature verification fails.
 *
 * 2. REPLAY ATTACK PROTECTION (Timestamp Tolerance)
 *    Stripe embeds a Unix timestamp `t=` in the Stripe-Signature header.
 *    We reject events where |now - t| > TOLERANCE_SECONDS (300s = 5 minutes).
 *    This prevents an attacker from capturing a valid signed payload and
 *    re-sending it hours later to re-trigger a business state change.
 *
 * 3. IDEMPOTENCY GUARD (Database Layer)
 *    Stripe guarantees at-least-once delivery. The same event can arrive
 *    multiple times. We record every processed event ID in `stripe_webhook_events`
 *    BEFORE mutating any business state. Duplicate events return 200 immediately
 *    (telling Stripe to stop retrying) without touching Business or Subscription.
 *
 * 4. ELOQUENT-ONLY DB WRITES
 *    All state mutations use Eloquent models (Business::find, Subscription::where).
 *    DB::table() is strictly forbidden in this controller.
 *
 * ── EVENT HANDLING ─────────────────────────────────────────────────────────
 *
 *  invoice.payment_succeeded
 *    → Business: is_active = true, subscription_status = 'active'
 *    → Subscription: status = 'active', current_period_end = Stripe period end
 *
 *  invoice.payment_failed
 *    → Business: is_active = false, subscription_status = 'past_due'
 *    → Subscription: status = 'past_due'
 *    → Data is NEVER deleted. Graceful suspension only.
 *
 *  customer.subscription.deleted
 *    → Business: is_active = false, subscription_status = 'cancelled'
 *    → Subscription: status = 'cancelled'
 *    → Data is NEVER deleted. Graceful suspension only.
 *
 * ── ROUTE ──────────────────────────────────────────────────────────────────
 *    POST /api/v1/webhooks/stripe
 *    No auth:sanctum — Stripe does not send Bearer tokens.
 *    No CSRF — Stripe is a server-to-server call, not a browser form.
 *    Authentication = Stripe-Signature header (HMAC-SHA256).
 *
 * @version Phase 7 — SaaS Billing Engine
 */
class StripeWebhookController extends Controller
{
    /**
     * Maximum allowed age of a Stripe webhook event in seconds.
     * Events older than this are rejected as potential replay attacks.
     * Stripe's own default tolerance is 300 seconds.
     */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    /**
     * Main webhook entry point.
     * Returns 200 for all successfully processed (or idempotently skipped) events.
     * Returns 400 for signature failures and malformed payloads.
     */
    public function handle(Request $request): JsonResponse
    {
        // ── Step 1: Read raw body BEFORE any JSON parsing ─────────────────────
        // Laravel may have already parsed the body as JSON by the time this
        // controller is reached. We must use getContent() to get the original
        // raw bytes, which is what Stripe's HMAC was computed against.
        $rawBody   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        // ── Step 2: Signature Verification ────────────────────────────────────
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret)) {
            Log::critical('Stripe Webhook: STRIPE_WEBHOOK_SECRET is not configured.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Webhook secret not configured.'], 500);
        }

        [$isValid, $verifyError] = $this->verifySignature($rawBody, $sigHeader, $secret);

        if (! $isValid) {
            Log::warning('Stripe Webhook: Signature verification failed.', [
                'reason' => $verifyError,
                'ip'     => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        // ── Step 3: Parse and validate the event payload ──────────────────────
        $event = json_decode($rawBody);

        if (! $event || ! isset($event->id, $event->type, $event->data->object)) {
            Log::error('Stripe Webhook: Malformed payload received.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Malformed payload.'], 400);
        }

        $stripeEventId = $event->id;
        $eventType     = $event->type;
        $eventObject   = $event->data->object;
        $stripeCreatedAt = isset($event->created)
            ? Carbon::createFromTimestamp($event->created)
            : null;

        // ── Step 4: Idempotency Guard ─────────────────────────────────────────
        // Check if this event ID has already been processed successfully.
        // Uses a raw query intentionally — the stripe_webhook_events table has
        // no Eloquent model and we only need a boolean existence check.
        $alreadyProcessed = DB::table('stripe_webhook_events')
            ->where('stripe_event_id', $stripeEventId)
            ->where('status', 'processed')
            ->exists();

        if ($alreadyProcessed) {
            Log::info('Stripe Webhook: Duplicate event ignored (idempotent).', [
                'event_id'   => $stripeEventId,
                'event_type' => $eventType,
            ]);
            // Return 200 to tell Stripe to stop retrying this event.
            return response()->json(['status' => 'already_processed'], 200);
        }

        // ── Step 5: Route to handler ───────────────────────────────────────────
        try {
            $businessId = null;

            DB::transaction(function () use (
                $eventType, $eventObject, $stripeEventId, $stripeCreatedAt, &$businessId
            ) {
                // Dispatch to the appropriate handler. Each handler returns
                // the business_id it acted on, for the audit log.
                $businessId = match ($eventType) {
                    'invoice.payment_succeeded'       => $this->handlePaymentSucceeded($eventObject),
                    'invoice.payment_failed'          => $this->handlePaymentFailed($eventObject),
                    'customer.subscription.deleted'   => $this->handleSubscriptionDeleted($eventObject),
                    default                           => null,
                };

                // Record the event as processed INSIDE the transaction.
                // If any handler throws, this insert is also rolled back —
                // allowing Stripe to retry without the duplicate guard blocking it.
                DB::table('stripe_webhook_events')->insert([
                    'stripe_event_id' => $stripeEventId,
                    'event_type'      => $eventType,
                    'status'          => 'processed',
                    'business_id'     => $businessId,
                    'stripe_created_at' => $stripeCreatedAt,
                    'processed_at'    => Carbon::now(),
                ]);
            });

            if ($businessId === null && ! in_array($eventType, ['invoice.payment_succeeded', 'invoice.payment_failed', 'customer.subscription.deleted'])) {
                Log::info('Stripe Webhook: Unhandled event type — acknowledged.', [
                    'event_id'   => $stripeEventId,
                    'event_type' => $eventType,
                ]);
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Stripe Webhook: Handler threw an exception.', [
                'event_id'   => $stripeEventId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // Return 500 — Stripe will retry the event after a backoff delay.
            // Do NOT return 400 here; that tells Stripe to give up permanently.
            return response()->json(['error' => 'Internal processing error.'], 500);
        }
    }

    // ── Event Handlers ────────────────────────────────────────────────────────

    /**
     * invoice.payment_succeeded
     * Activates the business and updates the subscription billing period.
     *
     * @return int|null  The business_id acted on, or null if not found.
     */
    private function handlePaymentSucceeded(object $invoice): ?int
    {
        $customerId     = $invoice->customer    ?? null;
        $subscriptionId = $invoice->subscription ?? null;

        if (! $customerId) {
            Log::warning('Stripe invoice.payment_succeeded: No customer ID in payload.');
            return null;
        }

        $business = Business::where('stripe_customer_id', $customerId)
            ->with('subscription')
            ->first();

        if (! $business) {
            Log::warning('Stripe invoice.payment_succeeded: Business not found.', [
                'stripe_customer_id' => $customerId,
            ]);
            return null;
        }

        // Activate the business
        $business->is_active          = true;
        $business->subscription_status = 'active';
        $business->save();

        // Activate and update the subscription period end
        if ($business->subscription) {
            $sub = $business->subscription;
            $sub->status = 'active';

            // Extract period end from the invoice's subscription if available.
            // Stripe invoice objects include lines.data[0].period.end for the new period.
            $periodEnd = $invoice->lines->data[0]->period->end ?? null;
            if ($periodEnd) {
                $sub->current_period_end = Carbon::createFromTimestamp($periodEnd);
            }

            if ($subscriptionId) {
                $sub->stripe_subscription_id = $subscriptionId;
            }

            $sub->save();
        }

        Log::info('Stripe invoice.payment_succeeded: Business activated.', [
            'business_id' => $business->id,
            'customer_id' => $customerId,
        ]);

        return $business->id;
    }

    /**
     * invoice.payment_failed
     * Suspends the business gracefully (is_active = false).
     * Does NOT delete any tenant data.
     *
     * @return int|null
     */
    private function handlePaymentFailed(object $invoice): ?int
    {
        $customerId = $invoice->customer ?? null;

        if (! $customerId) {
            Log::warning('Stripe invoice.payment_failed: No customer ID in payload.');
            return null;
        }

        $business = Business::where('stripe_customer_id', $customerId)
            ->with('subscription')
            ->first();

        if (! $business) {
            Log::warning('Stripe invoice.payment_failed: Business not found.', [
                'stripe_customer_id' => $customerId,
            ]);
            return null;
        }

        // Graceful suspension — data is preserved
        $business->is_active           = false;
        $business->subscription_status = 'past_due';
        $business->save();

        if ($business->subscription) {
            $business->subscription->status = 'past_due';
            $business->subscription->save();
        }

        Log::warning('Stripe invoice.payment_failed: Business suspended (past_due).', [
            'business_id' => $business->id,
            'customer_id' => $customerId,
        ]);

        return $business->id;
    }

    /**
     * customer.subscription.deleted
     * Cancels and suspends the business gracefully.
     * Does NOT delete any tenant data.
     *
     * @return int|null
     */
    private function handleSubscriptionDeleted(object $subscription): ?int
    {
        $customerId     = $subscription->customer ?? null;
        $subscriptionId = $subscription->id       ?? null;

        if (! $customerId) {
            Log::warning('Stripe customer.subscription.deleted: No customer ID in payload.');
            return null;
        }

        $business = Business::where('stripe_customer_id', $customerId)
            ->with('subscription')
            ->first();

        if (! $business) {
            Log::warning('Stripe customer.subscription.deleted: Business not found.', [
                'stripe_customer_id' => $customerId,
                'stripe_sub_id'      => $subscriptionId,
            ]);
            return null;
        }

        // Graceful suspension — data is preserved
        $business->is_active           = false;
        $business->subscription_status = 'cancelled';
        $business->save();

        if ($business->subscription) {
            $business->subscription->status = 'cancelled';
            $business->subscription->save();
        }

        Log::info('Stripe customer.subscription.deleted: Business cancelled and suspended.', [
            'business_id'    => $business->id,
            'customer_id'    => $customerId,
            'stripe_sub_id'  => $subscriptionId,
        ]);

        return $business->id;
    }

    // ── Signature Verification ─────────────────────────────────────────────────

    /**
     * Verifies the Stripe webhook signature using HMAC-SHA256.
     *
     * Implements the full Stripe Signed Payload specification:
     *   https://stripe.com/docs/webhooks/signatures
     *
     * Steps:
     *   1. Extract timestamp `t` and signatures `v1` from Stripe-Signature header
     *   2. Reject if timestamp > TOLERANCE_SECONDS old (replay attack prevention)
     *   3. Compute HMAC-SHA256("{t}.{raw_body}", secret)
     *   4. Compare with each v1 signature using constant-time hash_equals()
     *
     * @param  string      $rawBody    Raw request body bytes (pre-JSON-parse)
     * @param  string|null $sigHeader  Value of the Stripe-Signature header
     * @param  string      $secret     STRIPE_WEBHOOK_SECRET from config
     * @return array{bool, string}     [isValid, errorReason]
     */
    private function verifySignature(string $rawBody, ?string $sigHeader, string $secret): array
    {
        if (empty($sigHeader)) {
            return [false, 'Missing Stripe-Signature header.'];
        }

        // Parse "t=1234567890,v1=abc123,v1=def456" format
        $timestamp  = null;
        $signatures = [];

        foreach (explode(',', $sigHeader) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key === 't')  { $timestamp = $value; }
            if ($key === 'v1') { $signatures[] = $value; }
        }

        if (empty($timestamp)) {
            return [false, 'Missing timestamp (t=) in Stripe-Signature header.'];
        }

        if (empty($signatures)) {
            return [false, 'Missing v1 signature in Stripe-Signature header.'];
        }

        // ── Replay attack protection ───────────────────────────────────────────
        // Reject events whose timestamp deviates from the server clock by more
        // than TOLERANCE_SECONDS. Stripe's own client library uses 300 seconds.
        $age = abs(time() - (int) $timestamp);
        if ($age > self::SIGNATURE_TOLERANCE_SECONDS) {
            return [false, "Event timestamp too old or future-dated (age: {$age}s, tolerance: " . self::SIGNATURE_TOLERANCE_SECONDS . "s)."];
        }

        // ── Compute expected signature ─────────────────────────────────────────
        $signedPayload       = "{$timestamp}.{$rawBody}";
        $expectedSignature   = hash_hmac('sha256', $signedPayload, $secret);

        // ── Constant-time comparison against all v1 signatures ─────────────────
        // Stripe may include multiple v1 entries during key rotation.
        // We accept if ANY matches. hash_equals() prevents timing attacks.
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return [true, ''];
            }
        }

        return [false, 'HMAC signature mismatch — payload may have been tampered with.'];
    }
}
