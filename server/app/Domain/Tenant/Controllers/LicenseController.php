<?php

namespace App\Domain\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domain\Tenant\Models\License;
use App\Domain\Tenant\Services\LicenseKeyService;
use App\Domain\Tenant\Services\AuditLogger;

class LicenseController extends Controller
{
    public function getLicenses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        $licenses = License::with(['tenant', 'plan'])->orderBy('created_at', 'desc')->get();
        return response()->json($licenses);
    }

    public function generateLicense(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403, 'Unauthorized'); }
        $request->validate([
            'tenant_id' => 'required|exists:businesses,id',
            'plan_id' => 'required|exists:plans,id'
        ]);

        $plan = DB::table('plans')->where('id', $request->plan_id)->first();

        $licenseService = new LicenseKeyService();
        $licenseKey = $licenseService->generateKey($request->tenant_id, $request->plan_id);

        $license = License::create([
            'tenant_id' => $request->tenant_id,
            'plan_id' => $request->plan_id,
            'license_key' => $licenseKey,
            'status' => 'active',
            'device_limit' => $plan->device_limit ?? 1,
            'employee_limit' => $plan->employee_limit ?? 1,
            'expires_at' => $plan->interval === 'year' ? now()->addYear() : now()->addMonth(),
        ]);

        return response()->json([
            'message' => 'License key generated successfully',
            'license_key' => $licenseKey,
            'license' => $license->load(['tenant', 'plan'])
        ], 201);
    }

    public function toggleLicenseStatus(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        $license = License::findOrFail($id);
        $license->status = $license->status === 'active' ? 'suspended' : 'active';
        $license->save();

        if ($license->status === 'suspended') {
            AuditLogger::licenseRevoked($request->user(), $license);
        }

        return response()->json(['message' => 'License status updated', 'license' => $license->load(['tenant', 'plan'])]);
    }

    public function activateTenantLicense(Request $request)
    {
        $request->validate([
            'license_code' => 'required|string'
        ]);

        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $business = \App\Domain\Tenant\Models\Business::find($user->business_id);
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        // Validate the license key exists and is assigned to this business
        $license = License::where('license_key', $request->license_code)
                          ->where('tenant_id', $business->id)
                          ->first();

        if (!$license) {
            return response()->json(['message' => 'Invalid or unauthorized license code.'], 400);
        }

        if ($license->status !== 'active') {
            return response()->json(['message' => 'This license code is no longer active.'], 400);
        }

        if ($license->expires_at && \Carbon\Carbon::parse($license->expires_at)->isPast()) {
            return response()->json(['message' => 'This license code has expired.'], 400);
        }

        // Activate the business
        DB::transaction(function() use ($business, $license) {
            $business->subscription_status = 'Active';
            $business->is_active = true;
            $business->subscription_ends_at = $license->expires_at;
            $business->save();

            // Mark license as activated_at if null
            if (!$license->activated_at) {
                $license->activated_at = now();
                $license->save();
            }
        });

        return response()->json([
            'message' => 'License activated successfully! Your account is now fully operational.',
            'business' => $business
        ]);
    }
}
