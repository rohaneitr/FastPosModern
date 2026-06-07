<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SmsGatewayService
{
    /**
     * Send an SMS message to a customer dynamically using tenant settings.
     * 
     * @param string $phone
     * @param string $message
     * @param int|null $businessId
     * @return bool
     */
    public function sendSms(string $phone, string $message, ?int $businessId = null): bool
    {
        if (!$businessId && auth()->check()) {
            $businessId = auth()->user()->business_id;
        }

        if (!$businessId) {
            Log::warning("SmsGatewayService: Cannot send SMS. No business ID provided.");
            return false;
        }

        $business = DB::table('businesses')->where('id', $businessId)->first();
        if (!$business || !$business->settings) {
            Log::warning("SmsGatewayService: No settings found for business $businessId");
            return false;
        }

        $settings = json_decode($business->settings, true);
        $url = $settings['sms_gateway_url'] ?? '';
        $apiKey = $settings['sms_api_key'] ?? '';
        $senderId = $settings['sms_sender_id'] ?? '';

        if (empty($url)) {
            Log::info('--------------------------------------------------');
            Log::info("MOCK SMS DISPATCHED TO: {$phone}");
            Log::info("PAYLOAD: {$message}");
            Log::info('--------------------------------------------------');
            return true;
        }

        try {
            // Assume standard generic GET format for basic gateways (e.g., Greenweb, SMSQ)
            // Example: https://api.gateway.com/send?api_key=XYZ&sender_id=XYZ&phone=123&message=Hello
            $response = Http::timeout(10)->get($url, [
                'api_key' => $apiKey,
                'sender_id' => $senderId,
                'phone' => $phone,
                'message' => $message
            ]);

            if ($response->successful()) {
                Log::info("SMS Gateway Success [Business $businessId] to $phone");
                return true;
            }

            Log::error("SMS Gateway Failed [Business $businessId]: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("SMS Gateway Exception [Business $businessId]: " . $e->getMessage());
            return false;
        }
    }
}
