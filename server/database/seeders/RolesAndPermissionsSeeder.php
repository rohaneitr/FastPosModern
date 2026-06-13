<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * RolesAndPermissionsSeeder
 *
 * ENTERPRISE RBAC ARCHITECTURE — FastPOS Modern
 * -----------------------------------------------
 * Design Principles:
 *  1. Routes are ALWAYS gated by permission:X, NEVER by role:X (except SuperAdmin platform gate).
 *  2. Roles are collections of permissions — adding a new role requires ZERO code changes.
 *  3. Permissions are granular per resource + action (resource.action pattern).
 *  4. syncPermissions() is used (not givePermissionTo) so re-running is idempotent.
 *
 * Permission Naming Convention: {resource}.{action}
 *   e.g. products.view, products.create, products.delete
 *
 * @version 2.0.0
 * @updated 2026-06-12
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ── 0. Flush Spatie permission cache ────────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Define ALL permissions ────────────────────────────────────────────
        $permissions = [

            // ── PLATFORM (SuperAdmin only) ───────────────────────────────────────
            'platform.manage',          // Full SaaS control: maintenance, backups, global settings

            // ── TENANT / BUSINESS SETTINGS ───────────────────────────────────────
            'tenant.manage',            // Business profile, branding, locations, invoice layouts
            'tenant.billing',           // View/manage subscriptions, change plans
            'tenant.devices',           // Activate/revoke POS devices

            // ── USER & TEAM MANAGEMENT ───────────────────────────────────────────
            'users.view',               // List and view staff profiles
            'users.create',             // Create new staff accounts
            'users.edit',               // Edit staff details
            'users.delete',             // Delete/deactivate staff
            'users.invite',             // Send team invitation emails
            'roles.manage',             // Create/edit custom roles and assign permissions

            // ── PRODUCT CATALOG ───────────────────────────────────────────────────
            'products.view',            // Browse product list, view details
            'products.create',          // Add new products
            'products.edit',            // Edit product details, pricing
            'products.delete',          // Permanently delete products
            'products.import',          // Bulk CSV/Parquet import of products

            // ── INVENTORY ─────────────────────────────────────────────────────────
            'inventory.view',           // View stock levels, layers, history
            'inventory.adjust',         // Perform manual stock adjustments
            'inventory.transfer',       // Transfer stock between locations
            'inventory.labels',         // Print barcode/product labels

            // ── CATEGORIES, BRANDS, UNITS ─────────────────────────────────────────
            'categories.manage',        // Create, edit, delete product categories
            'brands.manage',            // Create, edit, delete brands
            'units.manage',             // Create, edit, delete units of measurement

            // ── POS / SALES ───────────────────────────────────────────────────────
            'pos.access',               // Open and operate the POS terminal (hardware-locked)
            'sales.view',               // View past transactions and sales history
            'sales.manage',             // Process sales, returns, sync offline transactions
            'sales.void',               // Void/cancel a completed transaction
            'sales.discount',           // Apply manual discounts on POS
            'registers.manage',         // Open and close cash registers

            // ── CONTACTS (CRM) ─────────────────────────────────────────────────────
            'contacts.view',            // View customer and supplier list
            'contacts.create',          // Create new contacts
            'contacts.edit',            // Edit contact details
            'contacts.delete',          // Delete contacts

            // ── PROCUREMENT ──────────────────────────────────────────────────────
            'suppliers.view',           // View supplier list
            'suppliers.create',         // Create new suppliers
            'suppliers.edit',           // Edit supplier details
            'suppliers.delete',         // Delete suppliers
            'purchases.view',           // View purchase orders
            'purchases.create',         // Create new purchase orders
            'purchases.edit',           // Edit purchase orders
            'purchases.delete',         // Delete/cancel purchase orders
            'purchases.receive',        // Receive inventory against a purchase order

            // ── FINANCE / ACCOUNTING ─────────────────────────────────────────────
            'reports.view',             // View financial dashboards, KPIs
            'reports.export',           // Export reports to PDF/CSV
            'accounting.view',          // View GL entries, trial balance, balance sheet
            'expenses.view',            // View expense records
            'expenses.create',          // Log new expenses
            'expenses.edit',            // Edit existing expenses
            'expenses.delete',          // Delete expense records

            // ── HR ────────────────────────────────────────────────────────────────
            'hr.employees.manage',      // Create, edit, delete employee records
            'hr.payroll.manage',        // Generate and process payroll
            'hr.attendance',            // Clock in/out attendance (any authenticated staff)
        ];

        // Create or ensure each permission exists (idempotent)
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName],
                ['guard_name' => 'sanctum']
            );
        }

        // ── 2. Define Roles and sync their permissions ────────────────────────────
        // Using syncPermissions() ensures re-running the seeder is safe and idempotent.

        // ────────────────────────────────────────────────────────────────────────
        // ROLE: SuperAdmin
        // Scope: Platform-wide. Bypasses tenant scope.
        // NOTE: SuperAdmin routes use 'role:SuperAdmin' middleware BY DESIGN.
        //       This is the only role-based gate that is intentional and correct.
        // ────────────────────────────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'sanctum']);
        $superAdmin->syncPermissions(Permission::all());

        // ────────────────────────────────────────────────────────────────────────
        // ROLE: BusinessAdmin
        // Scope: All operations within their own tenant. Full control.
        // ────────────────────────────────────────────────────────────────────────
        $businessAdmin = Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'sanctum']);
        $businessAdmin->syncPermissions([
            // Tenant management
            'tenant.manage',
            'tenant.billing',
            'tenant.devices',
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.invite',
            'roles.manage',
            // Catalog
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.import',
            // Inventory
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.labels',
            // Catalog sub-resources
            'categories.manage',
            'brands.manage',
            'units.manage',
            // POS / Sales
            'pos.access',
            'sales.view',
            'sales.manage',
            'sales.void',
            'sales.discount',
            'registers.manage',
            // CRM
            'contacts.view',
            'contacts.create',
            'contacts.edit',
            'contacts.delete',
            // Procurement
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            'purchases.view',
            'purchases.create',
            'purchases.edit',
            'purchases.delete',
            'purchases.receive',
            // Finance
            'reports.view',
            'reports.export',
            'accounting.view',
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            // HR
            'hr.employees.manage',
            'hr.payroll.manage',
            'hr.attendance',
        ]);

        // ────────────────────────────────────────────────────────────────────────
        // ROLE: Manager
        // Scope: Mid-level supervisor. Can manage day-to-day ops but not billing/roles.
        // ────────────────────────────────────────────────────────────────────────
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'sanctum']);
        $manager->syncPermissions([
            // Users (can view team, but cannot delete or manage roles)
            'users.view',
            'users.create',
            'users.edit',
            'users.invite',
            // Catalog (can manage products)
            'products.view',
            'products.create',
            'products.edit',
            'products.import',
            'categories.manage',
            'brands.manage',
            'units.manage',
            // Inventory
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.labels',
            // POS / Sales
            'pos.access',
            'sales.view',
            'sales.manage',
            'sales.void',
            'sales.discount',
            'registers.manage',
            // CRM
            'contacts.view',
            'contacts.create',
            'contacts.edit',
            // Procurement
            'suppliers.view',
            'purchases.view',
            'purchases.create',
            'purchases.edit',
            'purchases.receive',
            // Finance (view only)
            'reports.view',
            'expenses.view',
            'expenses.create',
            // HR (attendance + view)
            'hr.attendance',
        ]);

        // ────────────────────────────────────────────────────────────────────────
        // ROLE: Cashier
        // Scope: POS terminal operator. Minimal permissions.
        // ────────────────────────────────────────────────────────────────────────
        $cashier = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'sanctum']);
        $cashier->syncPermissions([
            'pos.access',
            'sales.view',
            'sales.manage',
            'sales.discount',
            'registers.manage',
            'contacts.view',
            'contacts.create',
            'products.view',
            'inventory.view',
            'inventory.labels',
            'hr.attendance',
        ]);

        // ────────────────────────────────────────────────────────────────────────
        // ROLE: InventoryManager
        // Scope: Stock and catalog management only.
        // ────────────────────────────────────────────────────────────────────────
        $inventoryManager = Role::firstOrCreate(['name' => 'InventoryManager', 'guard_name' => 'sanctum']);
        $inventoryManager->syncPermissions([
            'products.view',
            'products.create',
            'products.edit',
            'products.import',
            'categories.manage',
            'brands.manage',
            'units.manage',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.labels',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'purchases.view',
            'purchases.create',
            'purchases.edit',
            'purchases.receive',
            'hr.attendance',
        ]);

        // ────────────────────────────────────────────────────────────────────────
        // ROLE: Accountant
        // Scope: Financial reporting and expense management only.
        // ────────────────────────────────────────────────────────────────────────
        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'sanctum']);
        $accountant->syncPermissions([
            'sales.view',
            'reports.view',
            'reports.export',
            'accounting.view',
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'contacts.view',
            'suppliers.view',
            'purchases.view',
            'hr.attendance',
        ]);

        // ── 3. Re-sync SuperAdmin to capture any newly added permissions ──────────
        // This ensures SuperAdmin always has every permission, even if new ones are added.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $superAdmin->syncPermissions(Permission::all());
    }
}
