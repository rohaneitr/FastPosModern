<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    /**
     * Impersonate a tenant user (God Mode)
     * Strictly limited to SuperAdmins.
     */
    public function impersonate(Request $request, $tenant_id, $user_id = null)
    {
        $superAdmin = $request->user();

        // 1. Verify the requester is a SuperAdmin (Security Check)
        if (!$superAdmin->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized. Only SuperAdmins can impersonate.');
        }

        // 2. Find the target tenant
        $tenant = Business::findOrFail($tenant_id);

        // 3. Find the target user inside this specific tenant scope
        if ($user_id) {
            $targetUser = User::where('business_id', $tenant->id)->findOrFail($user_id);
        } else {
            // Default to the BusinessAdmin if no specific user is requested
            $targetUser = User::where('business_id', $tenant->id)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'BusinessAdmin');
                })->first();

            if (!$targetUser) {
                // Fallback to the first available user in the tenant
                $targetUser = User::where('business_id', $tenant->id)->firstOrFail();
            }
        }

        // 4. Generate a new Bearer token for the target user
        $token = $targetUser->createToken('impersonation_token')->plainTextToken;

        // 5. Aggressive Auditing / Logging
        Log::channel('single')->info('IMPERSONATION_EVENT_TRIGGERED', [
            'superadmin_id' => $superAdmin->id,
            'superadmin_email' => $superAdmin->email,
            'target_tenant_id' => $tenant->id,
            'target_user_id' => $targetUser->id,
            'target_user_email' => $targetUser->email,
            'ip_address' => $request->ip(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Optional: If there is an audit_logs table, insert directly
        try {
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'user_id' => $superAdmin->id,
                'business_id' => null, // SuperAdmin scope
                'action' => 'impersonate_tenant',
                'description' => "Impersonated user {$targetUser->id} in tenant {$tenant->id}",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently ignore if table doesn't exist
        }

        // 6. Return the token and user context
        return response()->json([
            'message' => 'Impersonation successful. God Mode activated.',
            'access_token' => $token,
            'user' => $targetUser->load(['roles', 'permissions', 'business'])
        ]);
    }
}
