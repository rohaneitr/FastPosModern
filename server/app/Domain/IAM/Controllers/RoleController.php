<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Get all roles scoped to the business
     */
    public function index(Request $request)
    {
        // Fetch roles. In a real Spatie setup, you'd use Role::where('business_id', ...)->get();
        // Here we mock the DB call to be safe if the Spatie package isn't fully scaffolded yet.
        $roles = DB::table('roles')
            ->where('business_id', $request->user()->business_id)
            ->get();

        return response()->json($roles);
    }

    /**
     * Store a new role and its permissions
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array', // array of permission names
        ]);

        try {
            DB::beginTransaction();

            $roleId = DB::table('roles')->insertGetId([
                'name' => $validated['name'] . '#' . $request->user()->business_id, // Spatie trick for multi-tenancy
                'business_id' => $request->user()->business_id,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign permissions
            foreach ($validated['permissions'] as $permissionName) {
                $perm = DB::table('permissions')->where('name', $permissionName)->first();
                if ($perm) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $perm->id,
                        'role_id' => $roleId
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Role created successfully', 'role_id' => $roleId], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all available permissions for the UI to render checkboxes
     */
    public function permissions()
    {
        // In a real scenario, this fetches all distinct permissions defined in the system
        $permissions = DB::table('permissions')->get();
        return response()->json($permissions);
    }
}
