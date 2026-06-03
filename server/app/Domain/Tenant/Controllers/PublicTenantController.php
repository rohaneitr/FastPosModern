<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicTenantController extends Controller
{
    /**
     * Resolve a business by its subdomain.
     * Used by the frontend to load tenant branding and context.
     */
    public function resolveSubdomain($subdomain)
    {
        $business = DB::table('businesses')
            ->where('subdomain', $subdomain)
            ->where('is_active', true)
            ->select('id', 'name', 'branding', 'created_at')
            ->first();

        if (!$business) {
            return response()->json(['message' => 'Tenant not found or inactive'], 404);
        }

        $business->branding = $business->branding ? json_decode($business->branding, true) : null;

        return response()->json([
            'business' => $business
        ]);
    }
}
