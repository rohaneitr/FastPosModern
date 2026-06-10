<?php

namespace App\Modules\Tenant\Controllers;

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
        $businessId = $request->user()->business_id;

        $business = DB::table('businesses')->where('id', $businessId)->first();
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

        DB::table('businesses')
            ->where('id', $request->user()->business_id)
            ->update($validated);

        return response()->json(['message' => 'Business settings updated successfully']);
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
        $rates = Cache::remember('settings:exchange_rates', 43200, function () {
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
            $response = Cache::remember('api:open_exchange_rates', 3600, function() {
                $res = Http::timeout(10)->get('https://open.er-api.com/v6/latest/USD');
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
                            ['base_currency' => 'USD', 'target_currency' => $code],
                            [
                                'rate' => $rate,
                                'source' => 'api',
                                'updated_at' => now(),
                            ]
                        );
                    }
                }

                // Clear the DB cache so the UI gets fresh rates
                Cache::forget('settings:exchange_rates');

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
        Cache::forget('settings:exchange_rates');

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
}

