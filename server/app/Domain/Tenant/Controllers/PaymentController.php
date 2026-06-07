<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domain\Tenant\Models\Plan;
use App\Domain\Tenant\Models\Business;
use App\Domain\Tenant\Models\TenantRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /**
     * Initiate Payment
     */
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'gateway' => 'required|in:sslcommerz,bkash'
        ]);

        $tenantId = $request->user()->business_id ?? $request->tenant_id;
        if (!$tenantId) {
            return response()->json(['message' => 'Tenant ID required'], 400);
        }

        $plan = Plan::findOrFail($request->plan_id);

        $activeDevicesCount = \App\Domain\Tenant\Models\License::where('tenant_id', $tenantId)->where('status', 'active')->count();
        if ($activeDevicesCount > $plan->device_limit) {
            return response()->json(['message' => "Please unlink devices before downgrading. Active: {$activeDevicesCount}, Allowed: {$plan->device_limit}"], 422);
        }

        $transactionId = 'TXN_' . strtoupper(uniqid());
        $amount = $plan->price;
        $currency = 'BDT';

        // Retrieve the owner's information for payload construction
        $owner = $request->user();
        $business = Business::find($tenantId);
        $customerName = $owner->first_name . ' ' . $owner->last_name ?: ($business->name ?? 'FastPOS Customer');
        $customerEmail = $owner->email;
        $customerPhone = $owner->phone ?? '01700000000';

        DB::table('payments')->insert([
            'tenant_id'      => $tenantId,
            'plan_id'        => $plan->id,
            'transaction_id' => $transactionId,
            'amount'         => $amount,
            'gateway'        => $request->gateway,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now()
        ]);

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $backendUrl = config('app.url', 'http://localhost:8002');

        if ($request->gateway === 'sslcommerz') {
            // ── SSLCommerz Initiation ─────────────────────────────────────────
            $post_data = [];
            $post_data['store_id'] = env('SSLCOMMERZ_STORE_ID');
            $post_data['store_passwd'] = env('SSLCOMMERZ_STORE_PASSWORD');
            $post_data['total_amount'] = $amount;
            $post_data['currency'] = $currency;
            $post_data['tran_id'] = $transactionId;
            $post_data['success_url'] = $backendUrl . '/api/v1/payments/callback/sslcommerz';
            $post_data['fail_url'] = $backendUrl . '/api/v1/payments/callback/sslcommerz';
            $post_data['cancel_url'] = $backendUrl . '/api/v1/payments/callback/sslcommerz';

            $post_data['cus_name'] = $customerName;
            $post_data['cus_email'] = $customerEmail;
            $post_data['cus_add1'] = 'Dhaka';
            $post_data['cus_city'] = 'Dhaka';
            $post_data['cus_postcode'] = '1000';
            $post_data['cus_country'] = 'Bangladesh';
            $post_data['cus_phone'] = $customerPhone;

            $post_data['shipping_method'] = 'NO';
            $post_data['product_name'] = $plan->name . ' Subscription';
            $post_data['product_category'] = 'Software License';
            $post_data['product_profile'] = 'non-physical-goods';

            $apiUrl = env('SSLCOMMERZ_TEST_MODE', true) 
                ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' 
                : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

            try {
                $response = Http::asForm()->post($apiUrl, $post_data);
                $result = $response->json();

                if (isset($result['status']) && $result['status'] === 'SUCCESS' && isset($result['GatewayPageURL'])) {
                    return response()->json([
                        'message'        => 'Payment initiated',
                        'transaction_id' => $transactionId,
                        'payment_url'    => $result['GatewayPageURL']
                    ]);
                }

                Log::error('SSLCommerz initiation failed', ['response' => $result]);
                return response()->json(['message' => 'Failed to connect to SSLCommerz'], 500);

            } catch (\Exception $e) {
                Log::error('SSLCommerz exception', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'SSLCommerz API Error: ' . $e->getMessage()], 500);
            }

        } elseif ($request->gateway === 'bkash') {
            // ── bKash Tokenized Checkout Initiation ───────────────────────────
            try {
                $token = $this->getBkashToken();
                if (!$token) {
                    return response()->json(['message' => 'Failed to obtain bKash token'], 500);
                }

                $bkashUrl = env('BKASH_TEST_MODE', true)
                    ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
                    : 'https://tokenized.pay.bka.sh/v1.2.0-beta';

                $createResponse = Http::withHeaders([
                    'Authorization' => $token,
                    'X-APP-Key' => env('BKASH_APP_KEY')
                ])->post($bkashUrl . '/tokenized/checkout/create', [
                    'mode' => '0011',
                    'payerReference' => ' ' . $customerPhone, // Must send a space or valid phone
                    'callbackURL' => $backendUrl . '/api/v1/payments/callback/bkash',
                    'amount' => $amount,
                    'currency' => $currency,
                    'intent' => 'sale',
                    'merchantInvoiceNumber' => $transactionId
                ]);

                $result = $createResponse->json();

                if (isset($result['statusCode']) && $result['statusCode'] === '0000' && isset($result['bkashURL'])) {
                    // Save paymentID temporarily against this transaction
                    DB::table('payments')->where('transaction_id', $transactionId)->update([
                        'gateway_payment_id' => $result['paymentID']
                    ]);

                    return response()->json([
                        'message'        => 'Payment initiated',
                        'transaction_id' => $transactionId,
                        'payment_url'    => $result['bkashURL']
                    ]);
                }

                Log::error('bKash create checkout failed', ['response' => $result]);
                return response()->json(['message' => 'Failed to create bKash checkout: ' . ($result['statusMessage'] ?? 'Unknown')], 500);

            } catch (\Exception $e) {
                Log::error('bKash exception', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'bKash API Error: ' . $e->getMessage()], 500);
            }
        }
    }

    /**
     * SSLCommerz Callback (POST)
     */
    public function sslcommerzCallback(Request $request)
    {
        $status = $request->input('status');
        $transactionId = $request->input('tran_id');

        // Only VALID or SUCCESS means successful payment
        $isSuccess = in_array($status, ['VALID', 'SUCCESS']);
        
        return $this->processCallback($transactionId, $isSuccess ? 'success' : 'failed', 'sslcommerz', $request->all());
    }

    /**
     * bKash Callback (GET)
     */
    public function bkashCallback(Request $request)
    {
        $paymentID = $request->query('paymentID');
        $status = $request->query('status'); // 'success', 'cancel', 'failure'

        if (!$paymentID) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/payment/failed?reason=missing_payment_id');
        }

        // Find the transaction ID associated with this paymentID
        $payment = DB::table('payments')->where('gateway_payment_id', $paymentID)->first();
        if (!$payment) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/payment/failed?reason=payment_not_found');
        }

        $transactionId = $payment->transaction_id;

        if ($status !== 'success') {
            return $this->processCallback($transactionId, 'failed', 'bkash', $request->all());
        }

        // We must execute the bKash payment to confirm it
        try {
            $token = $this->getBkashToken();
            $bkashUrl = env('BKASH_TEST_MODE', true)
                ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
                : 'https://tokenized.pay.bka.sh/v1.2.0-beta';

            $executeResponse = Http::withHeaders([
                'Authorization' => $token,
                'X-APP-Key' => env('BKASH_APP_KEY')
            ])->post($bkashUrl . '/tokenized/checkout/execute', [
                'paymentID' => $paymentID
            ]);

            $result = $executeResponse->json();

            if (isset($result['statusCode']) && $result['statusCode'] === '0000') {
                return $this->processCallback($transactionId, 'success', 'bkash', array_merge($request->all(), $result));
            } else {
                Log::error('bKash execute failed', ['response' => $result]);
                return $this->processCallback($transactionId, 'failed', 'bkash', array_merge($request->all(), $result));
            }

        } catch (\Exception $e) {
            Log::error('bKash execute exception', ['error' => $e->getMessage()]);
            return $this->processCallback($transactionId, 'failed', 'bkash', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Helper: Obtain bKash Token
     */
    private function getBkashToken()
    {
        return Cache::remember('bkash_token', 3500, function () {
            $bkashUrl = env('BKASH_TEST_MODE', true)
                ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
                : 'https://tokenized.pay.bka.sh/v1.2.0-beta';

            $response = Http::withHeaders([
                'username' => env('BKASH_USERNAME'),
                'password' => env('BKASH_PASSWORD')
            ])->post($bkashUrl . '/tokenized/checkout/token/grant', [
                'app_key' => env('BKASH_APP_KEY'),
                'app_secret' => env('BKASH_APP_SECRET')
            ]);

            $result = $response->json();
            if (isset($result['id_token'])) {
                return $result['id_token'];
            }
            
            Log::error('Failed to get bKash token', ['response' => $result]);
            return null;
        });
    }

    /**
     * Webhook Receiver (Simulated checkout)
     */
    public function handleWebhook(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required',
            'plan_id' => 'required',
            'amount' => 'required'
        ]);

        $tenantId = $request->tenant_id;
        $plan = Plan::findOrFail($request->plan_id);

        $subscription = DB::table('subscriptions')->where('business_id', $tenantId)->first();
        
        $currentEnd = $subscription && $subscription->current_period_end > now() 
            ? \Carbon\Carbon::parse($subscription->current_period_end) 
            : now();
            
        $newEnd = $plan->interval === 'year' ? $currentEnd->addYear() : $currentEnd->addMonth();

        DB::table('subscriptions')->updateOrInsert(
            ['business_id' => $tenantId],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => $newEnd,
                'updated_at' => now(),
            ]
        );

        DB::table('businesses')->where('id', $tenantId)->update([
            'is_active' => true,
            'plan_id' => $plan->id,
        ]);

        // Insert into subscription_payments
        if (\Illuminate\Support\Facades\Schema::hasTable('subscription_payments')) {
            DB::table('subscription_payments')->insert([
                'business_id' => $tenantId,
                'plan_id' => $plan->id,
                'amount' => $request->amount,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Subscription upgraded successfully']);
    }

    /**
     * ─── GATE 1 ──────────────────────────────────────────────────────────────
     * After a successful payment, we NO LONGER provision the subscription
     * immediately.  Instead we create a `tenant_requests` record with
     * status = 'pending' so a Super Admin can KYC-review and approve it first.
     * ─────────────────────────────────────────────────────────────────────────
     */
    private function processCallback($transactionId, $status, $gateway, $payload = [])
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        
        $payment = DB::table('payments')->where('transaction_id', $transactionId)->first();
        if (!$payment) {
            return redirect($frontendUrl . '/payment/failed?reason=invalid_transaction');
        }

        // ── Gateway validation (SSLCommerz) ──────────────────────────────────
        if ($gateway === 'sslcommerz' && $status === 'success') {
            $storePassword = env('SSLCOMMERZ_STORE_PASSWORD');
            if (!isset($payload['val_id'])) {
                return redirect($frontendUrl . '/payment/failed?reason=spoofing_detected');
            }
            try {
                $validationUrl = env('SSLCOMMERZ_TEST_MODE', true) 
                    ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
                    : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';

                $validationResponse = Http::get($validationUrl, [
                    'val_id'       => $payload['val_id'],
                    'store_id'     => env('SSLCOMMERZ_STORE_ID'),
                    'store_passwd' => $storePassword,
                    'format'       => 'json'
                ]);

                if (!$validationResponse->successful() || $validationResponse->json('status') !== 'VALID') {
                    return redirect($frontendUrl . '/payment/failed?reason=validation_failed');
                }
            } catch (\Exception $e) {
                Log::error('SSL validation error', ['error' => $e->getMessage()]);
                return redirect($frontendUrl . '/payment/failed?reason=validation_error');
            }
        }

        // ── Handle failed payment ─────────────────────────────────────────────
        if ($status !== 'success') {
            DB::table('payments')->where('id', $payment->id)->update(['status' => 'failed', 'updated_at' => now()]);
            return redirect($frontendUrl . '/payment/failed?txn=' . $transactionId);
        }

        // ── Mark payment successful ───────────────────────────────────────────
        // Prevent double processing
        if ($payment->status === 'success') {
            return redirect($frontendUrl . '/payment/success?txn=' . $transactionId);
        }

        DB::table('payments')->where('id', $payment->id)->update(['status' => 'success', 'updated_at' => now()]);

        $plan = Plan::find($payment->plan_id);

        // ── Determine the request type from the plan type ─────────────────────
        $requestType = match(true) {
            in_array($plan->plan_type ?? '', ['hybrid_offline_sync']) => 'hybrid',
            in_array($plan->plan_type ?? '', ['mobile_native'])       => 'mobile',
            default                                                   => 'web',
        };

        // Resolve business name if the tenant already has a record.
        $business     = Business::find($payment->tenant_id);
        $businessName = $business?->name ?? ('Applicant #' . $payment->tenant_id);
        
        // Lookup the user that initiated this for the applicant fields
        $user = DB::table('users')->where('business_id', $payment->tenant_id)->first();

        // ── Create the PENDING approval gate record ───────────────────────────
        $tenantRequest = TenantRequest::create([
            'tenant_id'      => $payment->tenant_id,
            'business_name'  => $businessName,
            'applicant_name' => $user ? ($user->first_name . ' ' . $user->last_name) : null,
            'applicant_email'=> $user ? $user->email : null,
            'type'           => $requestType,
            'plan_id'        => $plan->id,
            'transaction_id' => $transactionId,
            'status'         => TenantRequest::STATUS_PENDING,
        ]);

        Log::info('Gate 1: Tenant approval request created after payment', [
            'tenant_request_id' => $tenantRequest->id,
            'transaction_id'    => $transactionId,
            'tenant_id'         => $payment->tenant_id,
            'plan_id'           => $plan->id,
        ]);

        return redirect($frontendUrl . '/payment/success?txn=' . $transactionId . '&req_id=' . $tenantRequest->id);
    }
}
