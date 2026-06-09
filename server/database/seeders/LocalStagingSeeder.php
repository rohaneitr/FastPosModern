<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Modules\IAM\Models\User;
use Spatie\Permission\Models\Role;

class LocalStagingSeeder extends Seeder
{
    public function run(): void
    {
        // Clear caches
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Core God-Mode Roles
        Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);

        // 2. Super Admin Account
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@fastpos.com'],
            [
                'business_id' => null,
                'first_name' => 'System',
                'last_name' => 'Architect',
                'password' => Hash::make('password')
            ]
        );
        $superAdmin->assignRole('SuperAdmin');

        // 3. Setup Tech Retail Tenant
        $this->seedTechRetail($adminRole, $superAdmin->id);

        // 4. Setup Pharmacy Tenant
        $this->seedPharmacy($adminRole, $superAdmin->id);
    }

    private function seedTechRetail($adminRole, $ownerId): void
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Tech Retail HQ',
            'subdomain' => 'tech',
            'owner_id' => $ownerId,
            'is_active' => true,
            'subscription_status' => 'Active',
            'subscription_ends_at' => now()->addDays(30),
            'subscription_expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $locId = DB::table('locations')->insertGetId([
            'business_id' => $businessId, 'name' => 'Main Showroom', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('subscriptions')->insert([
            'business_id' => $businessId, 'plan_id' => 2, 'status' => 'active', 'current_period_end' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()
        ]);

        $user = User::updateOrCreate(
            ['email' => 'admin@tech.com'],
            [
                'business_id' => $businessId, 
                'first_name' => 'Tech', 
                'last_name' => 'Admin', 
                'password' => Hash::make('password')
            ]
        );
        $user->assignRole($adminRole);

        // Seed Hardware Products
        $products = [];
        for ($i = 1; $i <= 50; $i++) {
            $products[] = [
                'business_id' => $businessId,
                'name' => "Gaming GPU Series $i",
                'purchase_price' => 500.00,
                'selling_price' => 700.00,
                'sku' => "GPU-00$i",
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        DB::table('products')->insert($products);

        // Seed Serials
        $firstProduct = DB::table('products')->where('business_id', $businessId)->first();
        if ($firstProduct) {
            $tx1 = DB::table('transactions')->insertGetId(['business_id' => $businessId, 'location_id' => $locId, 'created_by' => $user->id, 'type' => 'opening_stock', 'status' => 'final', 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $txLineId = DB::table('transaction_lines')->insertGetId(['transaction_id' => $tx1, 'product_id' => $firstProduct->id, 'quantity' => 10, 'unit_price' => 500, 'created_at' => now(), 'updated_at' => now()]);

            $serials = [];
            for ($i = 1; $i <= 10; $i++) {
                $serials[] = [
                    'transaction_item_id' => $txLineId,
                    'serial_number' => "SN-GPU-990$i",
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            DB::table('transaction_item_serials')->insert($serials);
        }

        // Seed Transactions
        for ($i = 1; $i <= 20; $i++) {
            DB::table('transactions')->insert([
                'business_id' => $businessId, 'location_id' => $locId, 'created_by' => $user->id, 'type' => 'sell', 'status' => 'final', 'transaction_date' => now()->subDays(rand(1, 15)), 'created_at' => now(), 'updated_at' => now()
            ]);
        }
    }

    private function seedPharmacy($adminRole, $ownerId): void
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Global Pharma',
            'subdomain' => 'pharma',
            'owner_id' => $ownerId,
            'is_active' => true,
            'subscription_status' => 'Active',
            'subscription_ends_at' => now()->addDays(30),
            'subscription_expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $locId = DB::table('locations')->insertGetId([
            'business_id' => $businessId, 'name' => 'Downtown Clinic', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('subscriptions')->insert([
            'business_id' => $businessId, 'plan_id' => 3, 'status' => 'active', 'current_period_end' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()
        ]);

        $user = User::updateOrCreate(
            ['email' => 'admin@pharma.com'],
            [
                'business_id' => $businessId, 
                'first_name' => 'Pharma', 
                'last_name' => 'Admin', 
                'password' => Hash::make('password')
            ]
        );
        $user->assignRole($adminRole);

        // Seed Medicine Products
        $products = [];
        for ($i = 1; $i <= 50; $i++) {
            $products[] = [
                'business_id' => $businessId,
                'name' => "Paracetamol 500mg Batch $i",
                'purchase_price' => 5.00,
                'selling_price' => 10.00,
                'sku' => "MED-00$i",
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        DB::table('products')->insert($products);

        // Because medicine_batches schema is complex, we will simulate the logic natively via purchases
        $firstProduct = DB::table('products')->where('business_id', $businessId)->first();
        if ($firstProduct) {
            $contactId = DB::table('contacts')->insertGetId(['business_id' => $businessId, 'name' => 'Supplier A', 'type' => 'supplier', 'created_at' => now(), 'updated_at' => now()]);

            // Valid batch
            $pur1 = DB::table('purchases')->insertGetId(['business_id' => $businessId, 'contact_id' => $contactId, 'reference_no' => 'PUR-1', 'status' => 'received', 'purchase_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_lines')->insert(['purchase_id' => $pur1, 'product_id' => $firstProduct->id, 'quantity' => 100, 'purchase_price' => 5.00, 'sub_total' => 500, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('product_stocks')->insert(['product_id' => $firstProduct->id, 'location_id' => $locId, 'qty_available' => 100, 'expiry_date' => now()->addMonths(6), 'created_at' => now(), 'updated_at' => now()]);

            // Expired batch
            $pur2 = DB::table('purchases')->insertGetId(['business_id' => $businessId, 'contact_id' => $contactId, 'reference_no' => 'PUR-2', 'status' => 'received', 'purchase_date' => now()->subMonths(6), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_lines')->insert(['purchase_id' => $pur2, 'product_id' => $firstProduct->id, 'quantity' => 50, 'purchase_price' => 5.00, 'sub_total' => 250, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('product_stocks')->insert(['product_id' => $firstProduct->id, 'location_id' => $locId, 'qty_available' => 50, 'expiry_date' => now()->subDays(10), 'created_at' => now(), 'updated_at' => now()]);
        }

        // Active Sales
        for ($i = 1; $i <= 10; $i++) {
            DB::table('transactions')->insert([
                'business_id' => $businessId, 'location_id' => $locId, 'created_by' => $user->id, 'type' => 'sell', 'status' => 'final', 'transaction_date' => now()->subDays(rand(1, 15)), 'created_at' => now(), 'updated_at' => now()
            ]);
        }
    }
}
