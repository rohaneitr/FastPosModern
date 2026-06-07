<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PublicTenantController extends Controller
{
    /**
     * Resolve a tenant by their domain (subdomain or custom domain).
     * Expected param $domain can be "tenant1" or "pos.mycompany.com".
     */
    public function resolveSubdomain(Request $request, $domain)
    {
        // Cache the resolution to minimize DB hits on the edge
        $tenantConfig = Cache::remember("tenant_resolution_{$domain}", 300, function () use ($domain) {
            $business = DB::table('businesses')
                ->where('subdomain', $domain)
                ->orWhere('custom_domain', $domain)
                ->first();

            if (!$business) {
                return null;
            }

            // Retrieve basic public settings like branding, currency, etc.
            $currency = null;
            if ($business->currency_code) {
                $currency = DB::table('currencies')->where('code', $business->currency_code)->first();
            }

            return [
                'id' => $business->id,
                'name' => $business->name,
                'subdomain' => $business->subdomain,
                'custom_domain' => $business->custom_domain,
                'is_active' => (bool)$business->is_active,
                'branding' => $business->branding ? json_decode($business->branding, true) : null,
                'currency' => $currency ? [
                    'code' => $currency->code,
                    'symbol' => $currency->symbol,
                ] : null,
                'language' => $business->language ?? 'en',
            ];
        });

        if (!$tenantConfig) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        if (!$tenantConfig['is_active']) {
            return response()->json(['message' => 'Tenant is suspended or inactive'], 403);
        }

        return response()->json([
            'tenant' => $tenantConfig
        ]);
    }
}
