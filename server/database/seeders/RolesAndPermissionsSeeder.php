<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            // Platform Level
            'platform.manage',
            
            // Tenant Admin Level
            'tenant.manage',
            'users.manage',
            'users.create',
            'users.edit',
            'users.delete',
            
            // Business Operations
            'products.manage',
            'inventory.manage',
            'sales.manage',
            'reports.manage',
            'pos.access',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Create Roles and Assign Permissions

        // SUPER ADMIN
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin']);
        $superAdmin->givePermissionTo(Permission::all()); // Has everything, primarily 'platform.manage'

        // BUSINESS ADMIN (Tenant Owner)
        $businessAdmin = Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $businessAdmin->givePermissionTo([
            'tenant.manage',
            'users.manage',
            'users.create',
            'users.edit',
            'users.delete',
            'products.manage',
            'inventory.manage',
            'sales.manage',
            'reports.manage',
            'pos.access',
        ]);

        // CASHIER
        $cashier = Role::firstOrCreate(['name' => 'Cashier']);
        $cashier->givePermissionTo([
            'pos.access',
            'sales.manage',
        ]);

        // INVENTORY MANAGER
        $inventoryManager = Role::firstOrCreate(['name' => 'InventoryManager']);
        $inventoryManager->givePermissionTo([
            'products.manage',
            'inventory.manage',
        ]);

        // ACCOUNTANT
        $accountant = Role::firstOrCreate(['name' => 'Accountant']);
        $accountant->givePermissionTo([
            'reports.manage',
            'sales.manage',
        ]);
    }
}
