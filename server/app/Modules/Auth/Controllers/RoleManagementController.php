<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Exception;

class RoleManagementController extends Controller
{
    /**
     * RBAC Hardening: Prevents Tenant Admins from assigning permissions
     * for modules they have not purchased.
     */
    public function syncRolePermissions(Request $request, Role $role)
    {
        $tenant = $request->attributes->get('tenant');
        
        $request->validate([
            'permissions' => 'required|array'
        ]);

        $requestedPermissions = $request->input('permissions');

        // 1. Fetch Tenant's active modules
        $activeModules = json_decode($tenant->active_modules ?? '[]', true);

        // 2. Validate every requested permission
        foreach ($requestedPermissions as $permissionName) {
            // If the permission is tied to a module (e.g., 'module.manufacturing.create_order')
            if (str_starts_with($permissionName, 'module.')) {
                
                // Extract the module slug: 'module.manufacturing.xyz' -> 'manufacturing'
                $parts = explode('.', $permissionName);
                $moduleSlug = $parts[1] ?? null;

                if ($moduleSlug && !in_array($moduleSlug, $activeModules)) {
                    // BRUTAL HONESTY: Hard Reject
                    return response()->json([
                        'error_code' => 'UNAUTHORIZED_ENTITLEMENT',
                        'message' => "Forbidden: You cannot assign the permission [{$permissionName}] because your subscription does not include the [{$moduleSlug}] module."
                    ], 403);
                }
            }
        }

        // Safe to assign
        $role->syncPermissions($requestedPermissions);

        return response()->json(['message' => 'Role permissions updated securely.']);
    }
}
