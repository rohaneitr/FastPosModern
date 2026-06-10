<?php

namespace App\Modules\Finance\Payments;

interface PaymentGatewayInterface
{
    /**
     * Initiates the payment process and returns the redirect URL or payment payload.
     * 
     * @param string $transactionId Internal system transaction ID
     * @param float $amount Total amount to be paid
     * @param array $customerDetails Customer information required by gateway
     * @return array ['status' => 'success|failed', 'redirect_url' => string, 'gateway_transaction_id' => string]
     */
    public function pay(string $transactionId, float $amount, array $customerDetails): array;

    /**
     * Server-to-Server validation of the payment callback/webhook.
     * NEVER trust frontend payloads. Always query the gateway API.
     * 
     * @param array $requestData Data received from callback/webhook
     * @return array ['status' => 'verified|failed|pending', 'amount' => float, 'currency' => string, 'gateway_reference' => string]
     */
    public function verifyCallback(array $requestData): array;

    /**
     * Initiates a refund against a previously successful transaction.
     * 
     * @param string $gatewayTransactionId The reference ID provided by the gateway upon payment
     * @param float $amount Amount to refund
     * @return array ['status' => 'refunded|failed']
     */
    public function refund(string $gatewayTransactionId, float $amount): array;
}
