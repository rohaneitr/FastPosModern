<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Get all master settings for a business
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->hasRole('SuperAdmin') && is_null($user->business_id)) {
            $business = (object) Cache::store('redis')->get('global_system_settings', [
                'name' => 'FastPos Modern Global',
                'currency_code' => 'USD',
                'time_zone' => 'UTC',
                'language' => 'en',
            ]);
            return response()->json([
                'business' => $business,
                'locations' => [],
                'tax_rates' => [],
                'printers' => [],
                'invoice_layouts' => []
            ]);
        }

        $businessId = $user->business_id;

        $business = DB::table('businesses')->where('id', $businessId)->first();
        
        // Merge global overrides
        $globalSettings = Cache::store('redis')->get('global_system_settings');
        if ($globalSettings && isset($globalSettings['currency_code'])) {
            $business->currency_code = $globalSettings['currency_code'];
        }

        $locations = DB::table('locations')->where('business_id', $businessId)->get();
        $taxRates = DB::table('tax_rates')->where('business_id', $businessId)->get();
        $printers = DB::table('printers')->where('business_id', $businessId)->get();
        $invoiceLayouts = DB::table('invoice_layouts')->where('business_id', $businessId)->get();

        return response()->json([
            'business' => $business,
            'locations' => $locations,
            'tax_rates' => $taxRates,
            'printers' => $printers,
            'invoice_layouts' => $invoiceLayouts
        ]);
    }

    /**
     * Update business settings (name, timezone, currency, language)
     */
    public function updateBusiness(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'time_zone' => 'sometimes|string',
            'currency_code' => 'sometimes|string|size:3',
            'language' => 'sometimes|string|max:7',
            'currency_symbol_position' => 'sometimes|in:before,after',
            'currency_decimal_precision' => 'sometimes|integer|min:0|max:4',
            'currency_thousands_separator' => 'sometimes|string|max:1',
            'currency_decimal_separator' => 'sometimes|string|max:1',
        ]);

        $user = $request->user();

        if ($user->hasRole('SuperAdmin') && is_null($user->business_id)) {
            $currentSettings = Cache::store('redis')->get('global_system_settings', []);
            $newSettings = array_merge($currentSettings, $validated);
            Cache::store('redis')->forever('global_system_settings', $newSettings);
            return response()->json(['message' => 'Global system settings updated successfully']);
        }

        DB::table('businesses')
            ->where('id', $user->business_id)
            ->update($validated);

        return response()->json(['message' => 'Business settings updated successfully']);
    }

    /**
     * Update Invoice and Barcode settings
     */
    public function updateInvoiceSettings(Request $request)
    {
        $validated = $request->validate([
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_footer_text' => 'nullable|string|max:1000',
            'invoice_header_text' => 'nullable|string|max:1000',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'barcode_symbology' => 'nullable|string|in:CODE128,EAN13,UPCA',
            'show_logo' => 'nullable|boolean',
            'show_address' => 'nullable|boolean',
            'show_tax_number' => 'nullable|boolean',
            'show_due_balance' => 'nullable|boolean',
            'show_barcode' => 'nullable|boolean',
            'paper_size' => 'nullable|string|in:80mm,a4',
        ]);

        $business = DB::table('businesses')->where('id', $request->user()->business_id)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];

        $settings['invoice_prefix'] = $validated['invoice_prefix'] ?? 'INV-';
        $settings['invoice_footer_text'] = $validated['invoice_footer_text'] ?? '';
        $settings['invoice_header_text'] = $validated['invoice_header_text'] ?? '';
        $settings['default_tax_rate'] = $validated['default_tax_rate'] ?? 0;
        $settings['barcode_symbology'] = $validated['barcode_symbology'] ?? 'CODE128';
        $settings['show_logo'] = $validated['show_logo'] ?? true;
        $settings['show_address'] = $validated['show_address'] ?? true;
        $settings['show_tax_number'] = $validated['show_tax_number'] ?? false;
        $settings['show_due_balance'] = $validated['show_due_balance'] ?? true;
        $settings['show_barcode'] = $validated['show_barcode'] ?? true;
        $settings['paper_size'] = $validated['paper_size'] ?? '80mm';

        DB::table('businesses')
            ->where('id', $request->user()->business_id)
            ->update(['settings' => json_encode($settings)]);

        return response()->json(['message' => 'Invoice settings updated successfully', 'settings' => $settings]);
    }

    // ── Currency APIs ──

    /**
     * Get all available currencies
     */
    public function currencies()
    {
        $currencies = DB::table('currencies')->where('is_active', true)->get();
        return response()->json($currencies);
    }

    /**
     * Get current exchange rates
     */
    public function exchangeRates()
    {
        // Cache DB results in Redis for 12 hours (43200 seconds) to ensure instant loading
        $rates = Cache::store('redis')->remember('settings:exchange_rates', 43200, function () {
            return DB::table('exchange_rates')->get();
        });
        
        return response()->json($rates);
    }

    /**
     * Fetch live exchange rates and update database
     */
    public function updateExchangeRates()
    {
        try {
            // Rate limit the external API call (cache for 1 hour to prevent API exhaustion)
            $response = Cache::store('redis')->remember('api:open_exchange_rates', 3600, function() {
                $res = Http::timeout(10)->get('https://open.er-api.com/v6/latest/BDT');
                if ($res->successful()) {
                    return $res->json();
                }
                throw new \Exception('Failed to fetch from external API');
            });

            if ($response && isset($response['result']) && $response['result'] === 'success' && isset($response['rates'])) {
                // Get our supported currency codes
                $supportedCodes = DB::table('currencies')
                    ->where('is_active', true)
                    ->pluck('code')
                    ->toArray();

                foreach ($response['rates'] as $code => $rate) {
                    if (in_array($code, $supportedCodes)) {
                        DB::table('exchange_rates')->updateOrInsert(
                            ['base_currency' => 'BDT', 'target_currency' => $code],
                            [
                                'rate' => $rate,
                                'source' => 'api',
                                'updated_at' => now(),
                            ]
                        );
                    }
                }

                // Clear the DB cache so the UI gets fresh rates
                Cache::store('redis')->forget('settings:exchange_rates');

                $rates = DB::table('exchange_rates')->get();
                return response()->json([
                    'message' => 'Exchange rates updated successfully',
                    'rates' => $rates,
                    'updated_at' => now()->toISOString(),
                ]);
            }

            return response()->json([
                'message' => 'Failed to fetch rates from API. Using cached values.',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Exchange rate API unavailable: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Manually set an exchange rate
     */
    public function setExchangeRate(Request $request)
    {
        $validated = $request->validate([
            'base_currency' => 'required|string|size:3',
            'target_currency' => 'required|string|size:3',
            'rate' => 'required|numeric|min:0.00000001',
        ]);

        DB::table('exchange_rates')->updateOrInsert(
            [
                'base_currency' => $validated['base_currency'],
                'target_currency' => $validated['target_currency'],
            ],
            [
                'rate' => $validated['rate'],
                'source' => 'manual',
                'updated_at' => now(),
            ]
        );

        // Invalidate cache since a manual update occurred
        Cache::store('redis')->forget('settings:exchange_rates');

        return response()->json(['message' => 'Exchange rate updated successfully']);
    }

    // ── Language API ──

    /**
     * Update the user's language preference
     */
    public function updateLanguage(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|string|in:en,bn',
        ]);

        DB::table('users')
            ->where('id', $request->user()->id)
            ->update(['language' => $validated['language']]);

        return response()->json(['message' => 'Language preference updated']);
    }

    // ── White-Label Branding ──

    /**
     * Get branding configuration for the tenant.
     */
    public function getBranding(Request $request)
    {
        $business = DB::table('businesses')
            ->where('id', $request->user()->business_id)
            ->select('id', 'name', 'branding')
            ->first();

        $branding = $business->branding ? json_decode($business->branding, true) : [
            'logo_url' => null,
            'primary_color' => '#6366f1',
            'secondary_color' => '#1e1e30',
            'accent_color' => '#22c55e',
            'company_tagline' => '',
            'receipt_header' => '',
            'receipt_footer' => 'Thank you for your purchase!',
        ];

        return response()->json([
            'business_name' => $business->name,
            'branding' => $branding,
        ]);
    }

    /**
     * Update branding configuration.
     */
    public function updateBranding(Request $request)
    {
        $validated = $request->validate([
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'accent_color' => 'nullable|string|max:20',
            'company_tagline' => 'nullable|string|max:255',
            'receipt_header' => 'nullable|string|max:500',
            'receipt_footer' => 'nullable|string|max:500',
        ]);

        DB::table('businesses')
            ->where('id', $request->user()->business_id)
            ->update([
                'branding' => json_encode($validated),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Branding updated successfully', 'branding' => $validated]);
    }

    // ── Communication Settings ──
    
    public function testSmtpConnection(Request $request)
    {
        $validated = $request->validate([
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|string|max:10',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'required|string|max:255',
            'smtp_encryption' => 'nullable|string|max:50',
            'smtp_from_address' => 'required|email|max:255',
            'test_email' => 'required|email|max:255',
        ]);

        try {
            $transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $validated['smtp_host'],
                (int) $validated['smtp_port'],
                $validated['smtp_encryption'] === 'tls' || $validated['smtp_encryption'] === 'ssl'
            ))
            ->setUsername($validated['smtp_username'])
            ->setPassword($validated['smtp_password']);

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from($validated['smtp_from_address'])
                ->to($validated['test_email'])
                ->subject('FastPOS - SMTP Test Successful')
                ->text('Your SMTP configuration is working correctly.');

            $mailer->send($email);

            return response()->json(['message' => 'Test email sent successfully! Connection is valid.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'SMTP Connection failed: ' . $e->getMessage()], 400);
        }
    }

    public function getCommunicationSettings(Request $request)
    {
        $business = DB::table('businesses')->where('id', $request->user()->business_id)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];
        $commSettings = $business->communication_settings ? json_decode($business->communication_settings, true) : [];
        
        return response()->json([
            // Legacy SMS/WA in settings
            'sms_gateway_url' => $settings['sms_gateway_url'] ?? '',
            'sms_api_key' => $settings['sms_api_key'] ?? '',
            'sms_sender_id' => $settings['sms_sender_id'] ?? '',
            'whatsapp_api_url' => $settings['whatsapp_api_url'] ?? '',
            'whatsapp_token' => $settings['whatsapp_token'] ?? '',
            // New SMTP in communication_settings
            'smtp_host' => $commSettings['smtp_host'] ?? '',
            'smtp_port' => $commSettings['smtp_port'] ?? '',
            'smtp_username' => $commSettings['smtp_username'] ?? '',
            'smtp_password' => $commSettings['smtp_password'] ?? '',
            'smtp_encryption' => $commSettings['smtp_encryption'] ?? '',
            'smtp_from_address' => $commSettings['smtp_from_address'] ?? '',
        ]);
    }

    public function updateCommunicationSettings(Request $request)
    {
        $validated = $request->validate([
            'sms_gateway_url' => 'nullable|string|max:500',
            'sms_api_key' => 'nullable|string|max:255',
            'sms_sender_id' => 'nullable|string|max:100',
            'whatsapp_api_url' => 'nullable|string|max:500',
            'whatsapp_token' => 'nullable|string|max:1000',
            
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|string|max:10',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|string|max:50',
            'smtp_from_address' => 'nullable|email|max:255',
        ]);

        $business = DB::table('businesses')->where('id', $request->user()->business_id)->first();
        $settings = $business->settings ? json_decode($business->settings, true) : [];
        $commSettings = $business->communication_settings ? json_decode($business->communication_settings, true) : [];
        
        $settings['sms_gateway_url'] = $validated['sms_gateway_url'] ?? '';
        $settings['sms_api_key'] = $validated['sms_api_key'] ?? '';
        $settings['sms_sender_id'] = $validated['sms_sender_id'] ?? '';
        $settings['whatsapp_api_url'] = $validated['whatsapp_api_url'] ?? '';
        $settings['whatsapp_token'] = $validated['whatsapp_token'] ?? '';
        
        $commSettings['smtp_host'] = $validated['smtp_host'] ?? '';
        $commSettings['smtp_port'] = $validated['smtp_port'] ?? '';
        $commSettings['smtp_username'] = $validated['smtp_username'] ?? '';
        $commSettings['smtp_password'] = $validated['smtp_password'] ?? '';
        $commSettings['smtp_encryption'] = $validated['smtp_encryption'] ?? '';
        $commSettings['smtp_from_address'] = $validated['smtp_from_address'] ?? '';

        DB::table('businesses')
            ->where('id', $request->user()->business_id)
            ->update([
                'settings' => json_encode($settings), 
                'communication_settings' => json_encode($commSettings),
                'updated_at' => now()
            ]);

        return response()->json(['message' => 'Communication settings updated successfully']);
    }
}
