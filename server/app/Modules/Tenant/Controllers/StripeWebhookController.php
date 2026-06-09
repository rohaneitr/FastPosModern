<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhooks.
     */
    public function handle(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        
        if ($secret && !$this->verifySignature($request, $secret)) {
            Log::error('Stripe Webhook Error: Invalid Signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = json_decode($request->getContent());

        if (!$payload || !isset($payload->type)) {
            Log::error('Stripe Webhook Error: Invalid Payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        DB::beginTransaction();
        try {
            switch ($payload->type) {
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($payload->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($payload->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($payload->data->object);
                    break;

                default:
                    Log::info('Stripe Webhook: Unhandled event type ' . $payload->type);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stripe Webhook Error: Processing Failed', [
                'event' => $payload->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment invoice.
     */
    protected function handlePaymentSucceeded($invoice)
    {
        $customerId = $invoice->customer;
        $subscriptionId = $invoice->subscription;

        $business = Business::where('stripe_customer_id', $customerId)->first();

        if ($business) {
            $business->status = 'active';
            $business->subscription_status = 'active';
            $business->is_active = true;
            $business->save();
            
            Log::info("Stripe Webhook: Payment succeeded for Business {$business->id}");

            if ($business->subscription) {
                $business->subscription->status = 'active';
                // Period end can be extracted from subscription or invoice lines if needed
                $business->subscription->save();
            }
        } else {
            Log::warning("Stripe Webhook: Business not found for customer {$customerId}");
        }
    }

    /**
     * Handle failed payment invoice.
     */
    protected function handlePaymentFailed($invoice)
    {
        $customerId = $invoice->customer;
        $subscriptionId = $invoice->subscription;

        $business = Business::where('stripe_customer_id', $customerId)->first();

        if ($business) {
            $business->status = 'suspended';
            $business->subscription_status = 'past_due';
            $business->is_active = false;
            $business->save();
            
            Log::info("Stripe Webhook: Payment failed for Business {$business->id}");

            if ($business->subscription) {
                $business->subscription->status = 'past_due';
                $business->subscription->save();
            }
        } else {
            Log::warning("Stripe Webhook: Business not found for customer {$customerId}");
        }
    }

    /**
     * Handle deleted subscription.
     */
    protected function handleSubscriptionDeleted($subscriptionEvent)
    {
        $customerId = $subscriptionEvent->customer;
        $subscriptionId = $subscriptionEvent->id;

        $business = Business::where('stripe_customer_id', $customerId)->first();

        if ($business) {
            $business->status = 'cancelled';
            $business->subscription_status = 'cancelled';
            $business->is_active = false;
            $business->save();
            
            Log::info("Stripe Webhook: Subscription deleted for Business {$business->id}");

            if ($business->subscription) {
                $business->subscription->status = 'cancelled';
                $business->subscription->save();
            }
        } else {
            Log::warning("Stripe Webhook: Business not found for customer {$customerId}");
        }
    }

    /**
     * Verify Stripe Webhook Signature manually without requiring the Stripe SDK.
     */
    protected function verifySignature(Request $request, $secret)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (!$sigHeader) {
            return false;
        }

        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            $split = explode('=', $part, 2);
            if (count($split) === 2) {
                if ($split[0] === 't') {
                    $timestamp = $split[1];
                } elseif ($split[0] === 'v1') {
                    $signatures[] = $split[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }
}
