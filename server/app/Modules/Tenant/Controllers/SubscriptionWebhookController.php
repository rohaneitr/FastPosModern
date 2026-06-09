<?php

namespace App\Modules\Tenant\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class SubscriptionWebhookController
{
    /**
     * Handle incoming payment gateway webhooks securely.
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Signature'); // Example signature header
        
        $secret = env('WEBHOOK_SECRET');

        // 1. Cryptographic Validation
        if (!$signature || !$secret) {
            Log::channel('security')->critical('Webhook rejected: Missing signature or secret.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::channel('security')->critical('Webhook rejected: Signature mismatch.', ['ip' => $request->ip(), 'payload' => $payload]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($payload, true);
        $transactionId = $data['transaction_id'] ?? null;
        $businessId = $data['business_id'] ?? null;
        $amount = $data['amount'] ?? 0;
        $monthsAdded = $data['months_added'] ?? 1;

        if (!$transactionId || !$businessId) {
            return response()->json(['error' => 'Invalid payload format'], 400);
        }

        try {
            DB::transaction(function () use ($transactionId, $businessId, $amount, $monthsAdded) {
                // 2. Idempotent Ledger Insertion
                // Will throw QueryException if transaction_id exists
                DB::table('saas_payment_ledgers')->insert([
                    'business_id' => $businessId,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'payment_method' => 'Stripe/SSLCommerz',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // 3. Extend Subscription Lock
                $subscription = DB::table('saas_subscriptions')->where('business_id', $businessId)->lockForUpdate()->first();
                
                if ($subscription) {
                    $currentValidUntil = \Carbon\Carbon::parse($subscription->valid_until);
                    $newValidUntil = $currentValidUntil->isPast() ? now()->addMonths($monthsAdded) : $currentValidUntil->addMonths($monthsAdded);

                    DB::table('saas_subscriptions')->where('id', $subscription->id)->update([
                        'valid_until' => $newValidUntil,
                        'status' => 'Active',
                        'updated_at' => now()
                    ]);
                }
            });

            return response()->json(['status' => 'success']);

        } catch (QueryException $e) {
            // Duplicate Entry (1062) Error Code
            if ($e->errorInfo[1] == 1062) {
                // Webhook already processed, return 200 gracefully
                Log::info('Webhook duplicate gracefully ignored', ['transaction_id' => $transactionId]);
                return response()->json(['status' => 'success', 'message' => 'Already processed']);
            }
            throw $e;
        }
    }
}
