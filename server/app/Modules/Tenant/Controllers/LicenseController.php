<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\License;
use App\Modules\Tenant\Services\AuditLogger;

class LicenseController extends Controller
{
    private function generateCleanKey(): string
    {
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $key = '';
        for ($i = 0; $i < 4; $i++) {
            $block = '';
            for ($j = 0; $j < 4; $j++) {
                $block .= $pool[random_int(0, strlen($pool) - 1)];
            }
            $key .= $block . ($i < 3 ? '-' : '');
        }
        return $key;
    }

    public function getLicenses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('SuperAdmin')) { abort(403); }
        $licenses = License::with(['tenant', 'plan'])
            ->withCount(['deviceActivations as active_devices_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->with(['deviceActivations' => function ($q) {
                $q->where('status', 'active')->select('id', 'license_key', 'device_fingerprint', 'activated_at', 'last_synced_at');
            }])
            ->orderBy('created_at', 'desc')
            ->get();
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
        $deviceLimit = $plan->device_limit ?? 1;
        $expiresAt   = $plan->interval === 'year' ? \Carbon\Carbon::now()->addYear() : \Carbon\Carbon::now()->addMonth();

        $licenseKey = $this->generateCleanKey();

        $license = License::create([
            'tenant_id'      => $request->tenant_id,
            'plan_id'        => $request->plan_id,
            'license_key'    => $licenseKey,
            'status'         => 'active',
            'device_limit'   => $deviceLimit,
            'employee_limit' => $plan->employee_limit ?? 1,
            'expires_at'     => $expiresAt,
        ]);

        // Persist license_key to businesses table and mark as active
        DB::table('businesses')
            ->where('id', $request->tenant_id)
            ->update([
                'license_key' => $licenseKey,
                'status'      => 'active',
                'updated_at'  => now(),
            ]);

        return response()->json([
            'message'     => 'License key generated successfully',
            'license_key' => $licenseKey,
            'license'     => $license->load(['tenant', 'plan'])
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
        $request->merge(['license_key' => $request->license_code]);
        return $this->activate($request);
    }

    public function activate(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string'
        ]);

        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $business = \App\Modules\Tenant\Models\Business::find($user->business_id);
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $license = License::where('license_key', $request->license_key)->first();

        if (!$license) {
            return response()->json(['message' => 'Invalid license key.'], 400);
        }

        if ($license->expires_at && now()->greaterThan($license->expires_at)) {
            return response()->json(['message' => 'This license key has expired.'], 400);
        }

        if ($license->tenant_id !== $business->id) {
            return response()->json(['message' => 'This license key is not assigned to your business.'], 403);
        }

        if ($license->status !== 'active') {
            return response()->json(['message' => 'This license key has been suspended or revoked.'], 403);
        }

        $plan = DB::table('plans')->where('id', $license->plan_id)->first();
        $features = $plan ? json_decode($plan->enabled_modules, true) : [];

        DB::transaction(function() use ($business, $license, $features) {
            $business->status = 'active';
            $business->subscription_status = 'Active';
            $business->is_active = true;
            $business->license_key = $license->license_key;
            $business->active_modules = $features ?? [];
            $business->device_limit = $license->device_limit ?? 1;
            
            if ($license->expires_at) {
                $business->subscription_ends_at = $license->expires_at;
            }
            
            $business->save();

            if (!$license->activated_at) {
                $license->activated_at = now();
                $license->save();
            }
        });

        return response()->json([
            'message' => 'License activated successfully! Your account is now active.',
            'business' => $business
        ]);
    }
}
