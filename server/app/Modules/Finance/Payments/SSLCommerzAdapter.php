<?php

namespace App\Modules\Finance\Payments;

use Illuminate\Support\Facades\Http;
use Exception;

class SSLCommerzAdapter implements PaymentGatewayInterface
{
    protected string $storeId;
    protected string $storePassword;
    protected string $baseUrl;

    public function __construct()
    {
        // Mock credentials injection (typically loaded from config/env per tenant or system)
        $this->storeId = config('sslcommerz.store_id');
        $this->storePassword = config('sslcommerz.store_password');
        $this->baseUrl = config('sslcommerz.is_sandbox') 
            ? 'https://sandbox.sslcommerz.com' 
            : 'https://securepay.sslcommerz.com';
    }

    public function pay(string $transactionId, float $amount, array $customerDetails): array
    {
        // ... Logic to hit SSLCommerz Session API and get gateway URL ...
        // Returns the redirect URL for the frontend.
        return [
            'status' => 'success',
            'redirect_url' => 'https://sandbox.sslcommerz.com/gwprocess/v4/gw.php?Q=pay&SESSIONKEY=MOCK_SESSION',
            'gateway_transaction_id' => 'SSL-' . uniqid()
        ];
    }

    public function verifyCallback(array $requestData): array
    {
        if (!isset($requestData['val_id'])) {
            throw new Exception("Invalid Callback Payload. Missing val_id.");
        }

        // BRUTAL HONESTY: Server-to-Server Verification (Zero-Trust)
        // We completely ignore the 'status=VALID' flag in the POST request. 
        // We ONLY trust the response from the SSLCommerz Validation API.
        $response = Http::get("{$this->baseUrl}/validator/api/validationserverAPI.php", [
            'val_id' => $requestData['val_id'],
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json'
        ]);

        if ($response->failed()) {
            throw new Exception("SSLCommerz Validation API Unreachable.");
        }

        $apiData = $response->json();

        if (isset($apiData['status']) && $apiData['status'] === 'VALID') {
            return [
                'status' => 'verified',
                'amount' => (float) $apiData['amount'],
                'currency' => $apiData['currency'],
                'gateway_reference' => $apiData['bank_tran_id']
            ];
        }

        return [
            'status' => 'failed',
            'amount' => 0.00,
            'currency' => 'BDT',
            'gateway_reference' => $requestData['tran_id'] ?? 'UNKNOWN'
        ];
    }

    public function refund(string $gatewayTransactionId, float $amount): array
    {
        return ['status' => 'refunded'];
    }
}
