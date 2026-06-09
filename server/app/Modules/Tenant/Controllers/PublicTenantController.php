<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicTenantController extends Controller
{
    /**
     * Resolve a business by its subdomain.
     * Used by the frontend to load tenant branding and context.
     */
    public function resolveSubdomain(Request $request, $subdomain)
    {
        \Illuminate\Support\Facades\Log::info('Tenant Resolution Request: ' . $subdomain . ' from Host: ' . $request->getHost());
        // Clean subdomain/domain strings to handle raw hosts
        $cleanDomain = str_replace(['.localhost', ':3000', ':8000'], '', $subdomain);
        \Illuminate\Support\Facades\Log::info('Cleaned domain: ' . $cleanDomain);

        $business = DB::table('businesses')
            ->where('subdomain', $cleanDomain)
            ->where('is_active', true)
            ->select('id', 'name', 'branding', 'created_at')
            ->first();

        if (!$business) {
            return response()->json(['message' => 'Tenant not found or inactive'], 404);
        }

        $business->branding = $business->branding ? json_decode($business->branding, true) : null;

        return response()->json([
            'tenant' => $business
        ]);
    }
}
