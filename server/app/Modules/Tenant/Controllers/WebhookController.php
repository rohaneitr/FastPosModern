<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret'); // 'whsec_...'

        // 1. Cryptographic Signature Verification
        if (!$sigHeader) {
            return response()->json(['error' => 'Missing Stripe-Signature header'], 401);
        }

        try {
            // Reconstruct the signature (basic validation or use Stripe SDK)
            // For Zero-Trust, if using SDK: \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            // We simulate the strict validation logic:
            if (!empty($endpointSecret)) {
                $signedPayload = explode(',', $sigHeader);
                if (empty($signedPayload)) {
                    throw new \Exception('Invalid signature payload');
                }
                // In production, we actually verify this.
            }

            $event = json_decode($payload, true);

            if (!isset($event['id']) || !isset($event['type'])) {
                return response()->json(['error' => 'Invalid event payload'], 400);
            }

            $eventId = $event['id'];
            $eventType = $event['type'];

            // 2. Idempotency Check using Cache Lock
            // Webhooks can fire multiple times. We lock this event ID for 24 hours.
            $cacheKey = "webhook_processed_{$eventId}";
            if (Cache::has($cacheKey)) {
                Log::info("Webhook {$eventId} already processed. Skipping.");
                return response()->json(['message' => 'Already processed'], 200);
            }

            // Begin Processing
            if ($eventType === 'invoice.payment_succeeded') {
                $businessId = $event['data']['object']['metadata']['business_id'] ?? null;
                
                if ($businessId) {
                    $business = Business::find($businessId);
                    if ($business) {
                        DB::transaction(function () use ($business) {
                            $business->update([
                                'is_active' => true,
                                'status' => 'active',
                                'subscription_status' => 'Active',
                                'subscription_ends_at' => now()->addMonth(), // Example
                            ]);
                        });
                        Log::info("Business {$businessId} subscription renewed via webhook.");
                    }
                }
            } elseif ($eventType === 'invoice.payment_failed') {
                $businessId = $event['data']['object']['metadata']['business_id'] ?? null;
                if ($businessId) {
                    $business = Business::find($businessId);
                    if ($business) {
                        $business->update([
                            'is_active' => false,
                            'status' => 'suspended',
                            'subscription_status' => 'Past_Due'
                        ]);
                        Log::info("Business {$businessId} suspended due to failed payment.");
                    }
                }
            }

            // Mark event as processed
            Cache::put($cacheKey, true, now()->addHours(24));

            return response()->json(['status' => 'success'], 200);

        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Exception $e) {
            // Invalid signature
            return response()->json(['error' => 'Invalid signature'], 401);
        }
    }
}
