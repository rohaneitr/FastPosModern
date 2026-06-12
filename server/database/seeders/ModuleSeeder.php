<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['name' => 'Core POS', 'slug' => 'core', 'description' => 'Essential point of sale operations.', 'category' => 'Core ERP'],
            ['name' => 'Accounting & Finance', 'slug' => 'finance', 'description' => 'General ledger, charts of accounts, financial reports.', 'category' => 'Core ERP'],
            
            ['name' => 'Advanced Inventory', 'slug' => 'inventory', 'description' => 'Multi-warehouse, stock transfers, barcode generation.', 'category' => 'Inventory Management'],
            ['name' => 'Procurement & Purchasing', 'slug' => 'procurement', 'description' => 'Purchase orders, supplier management.', 'category' => 'Inventory Management'],
            ['name' => 'Manufacturing & BOM', 'slug' => 'manufacturing', 'description' => 'Bill of materials, production workflows.', 'category' => 'Inventory Management'],

            ['name' => 'HR & Payroll', 'slug' => 'hr', 'description' => 'Staff attendance, leave management, payroll processing.', 'category' => 'HR & Payroll'],

            ['name' => 'Sales & Quotations', 'slug' => 'sales', 'description' => 'B2B sales, wholesale management, quotations.', 'category' => 'Sales & POS'],
            ['name' => 'CRM & Loyalty', 'slug' => 'crm', 'description' => 'Customer retention, loyalty points, SMS marketing.', 'category' => 'Sales & POS'],
            
            ['name' => 'Multi Currency', 'slug' => 'multi_currency', 'description' => 'Process transactions in multiple currencies.', 'category' => 'Add-ons & Utilities'],
            ['name' => 'Advanced RMA', 'slug' => 'advanced_rma', 'description' => 'Return merchandise authorization and warranties.', 'category' => 'Add-ons & Utilities'],
            ['name' => 'Serial & IMEI Tracking', 'slug' => 'serial_tracking', 'description' => 'Advanced item-level tracking for electronics and warranties.', 'category' => 'Add-ons & Utilities'],
            
            ['name' => 'Pharmacy Vertical', 'slug' => 'pharmacy', 'description' => 'Prescription management, batch expiry, and FEFO routing.', 'category' => 'Verticals'],
            ['name' => 'Restaurant Vertical', 'slug' => 'restaurant', 'description' => 'KDS, table management, and recipe-based inventory depletion.', 'category' => 'Verticals'],
            ['name' => 'Hardware Builder', 'slug' => 'hardware_builder', 'description' => 'Compatibility engines for custom PC or CCTV assembly.', 'category' => 'Verticals'],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['name' => $module['name']],
                [
                    'slug' => $module['slug'],
                    'description' => $module['description'],
                    'category' => $module['category'],
                    'updated_at' => now(),
                ]
            );
        }
    }
}
