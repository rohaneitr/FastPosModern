<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Payments\PaymentGatewayInterface;
use App\Modules\Finance\Events\PaymentReceivedEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentCallbackController extends Controller
{
    /**
     * Handles webhook and redirect callbacks from Payment Gateways.
     */
    public function handleCallback(Request $request, string $gateway, PaymentGatewayInterface $gatewayAdapter)
    {
        $payload = $request->all();
        $internalTransactionId = $payload['tran_id'] ?? null; // Adjust based on gateway spec

        if (!$internalTransactionId) {
            return response()->json(['message' => 'Missing transaction reference.'], 400);
        }

        // 1. Idempotency Lock
        // A distributed lock that prevents double-billing if Webhook and Frontend Redirect hit simultaneously.
        $lock = Cache::lock("payment_verification_{$internalTransactionId}", 10);

        if (!$lock->get()) {
            // Lock is held by another process currently verifying this exact transaction.
            // Return 200 to gateway so it doesn't retry frantically.
            return response()->json(['message' => 'Processing concurrent callback...'], 200);
        }

        try {
            // 2. Check Database Idempotency (Has it already been marked Paid?)
            $invoice = DB::table('transactions')->where('transaction_number', $internalTransactionId)->first();
            
            if (!$invoice) {
                throw new Exception("Invoice {$internalTransactionId} not found in system.");
            }

            if ($invoice->payment_status === 'Paid') {
                // Already processed. Return 200 OK.
                return response()->json(['message' => 'Already Paid.'], 200);
            }

            // 3. Server-to-Server Validation (Zero-Trust)
            $verificationResult = $gatewayAdapter->verifyCallback($payload);

            if ($verificationResult['status'] === 'verified') {
                
                // 4. Atomic Ledger & DB Update
                DB::transaction(function () use ($invoice, $verificationResult, $gateway) {
                    
                    // Mark as Paid
                    DB::table('transactions')->where('id', $invoice->id)->update([
                        'payment_status' => 'Paid',
                        'amount_paid' => $verificationResult['amount'],
                        'gateway_reference' => $verificationResult['gateway_reference'],
                        'payment_method' => $gateway,
                        'updated_at' => now()
                    ]);

                    // Inject into General Ledger
                    event(new PaymentReceivedEvent([
                        'businessId' => $invoice->business_id,
                        'transactionId' => $invoice->id,
                        'amount' => $verificationResult['amount'],
                        'gateway' => $gateway,
                        'reference' => $verificationResult['gateway_reference']
                    ]));
                });

                // Successful redirect for user browser
                return redirect("/payment/success?invoice={$internalTransactionId}");
            } else {
                // Fraud or Failure
                DB::table('transactions')->where('id', $invoice->id)->update([
                    'payment_status' => 'Failed',
                    'updated_at' => now()
                ]);
                return redirect("/payment/failed?invoice={$internalTransactionId}");
            }

        } catch (Exception $e) {
            // Log error for manual reconciliation
            return response()->json(['message' => 'Internal Server Error during verification.'], 500);
        } finally {
            // Always release the lock
            $lock->release();
        }
    }
}
