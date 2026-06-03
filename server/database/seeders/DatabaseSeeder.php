<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Seed Roles and Permissions First
        $this->call(RolesAndPermissionsSeeder::class);

        // 1b. Seed Currencies and Exchange Rates
        $this->call(CurrencySeeder::class);

        // 2. Create the Super Admin (Platform Owner - No Business)
        $superAdminId = DB::table('users')->insertGetId([
            'username' => 'superadmin',
            'first_name' => 'System',
            'last_name' => 'Owner',
            'email' => 'superadmin@fastpos.com',
            'password' => Hash::make('Secret@12'),
            'user_type' => 'super_admin',
            'business_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        $superAdminUser = \App\Domain\IAM\Models\User::find($superAdminId);
        $superAdminUser->assignRole('SuperAdmin');

        // 3. Create a Business (Tenant) and set the Business Admin
        $businessAdminId = DB::table('users')->insertGetId([
            'username' => 'admin',
            'first_name' => 'Tenant',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret@12'),
            'user_type' => 'business_admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'FastPos Demo Corp',
            'owner_id' => $businessAdminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Update the business admin with their business_id and assign role
        DB::table('users')->where('id', $businessAdminId)->update(['business_id' => $businessId]);
        $businessAdminUser = \App\Domain\IAM\Models\User::find($businessAdminId);
        $businessAdminUser->assignRole('BusinessAdmin');


        // 4. Create Staff Users (Cashier, Inventory Manager, Accountant)
        
        // Cashier
        $cashierId = DB::table('users')->insertGetId([
            'username' => 'cashier_john',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'cashier@example.com',
            'password' => Hash::make('Secret@12'),
            'user_type' => 'user',
            'business_id' => $businessId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cashierUser = \App\Domain\IAM\Models\User::find($cashierId);
        $cashierUser->assignRole('Cashier');

        // Inventory Manager
        $inventoryId = DB::table('users')->insertGetId([
            'username' => 'inv_sarah',
            'first_name' => 'Sarah',
            'last_name' => 'Smith',
            'email' => 'inventory@example.com',
            'password' => Hash::make('Secret@12'),
            'user_type' => 'user',
            'business_id' => $businessId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $inventoryUser = \App\Domain\IAM\Models\User::find($inventoryId);
        $inventoryUser->assignRole('InventoryManager');

        // Accountant
        $accountantId = DB::table('users')->insertGetId([
            'username' => 'acc_mike',
            'first_name' => 'Mike',
            'last_name' => 'Ross',
            'email' => 'accountant@example.com',
            'password' => Hash::make('Secret@12'),
            'user_type' => 'user',
            'business_id' => $businessId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $accountantUser = \App\Domain\IAM\Models\User::find($accountantId);
        $accountantUser->assignRole('Accountant');


        // 5. Create a Location
        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Main Store',
            'city' => 'New York',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 6. Create a Unit
        $unitId = DB::table('units')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Pieces',
            'short_name' => 'pcs',
            'allow_decimal' => false,
            'created_by' => $businessAdminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 7. Create some Products
        $products = [
            ['name' => 'Wireless Headphones', 'sku' => 'WH-001', 'price' => 129.99],
            ['name' => 'Mechanical Keyboard', 'sku' => 'MK-002', 'price' => 149.50],
            ['name' => 'USB-C Hub', 'sku' => 'UH-003', 'price' => 45.00],
            ['name' => 'Ergonomic Mouse', 'sku' => 'EM-004', 'price' => 79.99],
            ['name' => '27" 4K Monitor', 'sku' => 'MON-005', 'price' => 349.00],
        ];

        foreach ($products as $p) {
            $productId = DB::table('products')->insertGetId([
                'business_id' => $businessId,
                'name' => $p['name'],
                'type' => 'single',
                'unit_id' => $unitId,
                'sku' => $p['sku'],
                'enable_stock' => true,
                'created_by' => $businessAdminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create Variation (Pricing)
            DB::table('variations')->insert([
                'product_id' => $productId,
                'name' => 'DUMMY',
                'sub_sku' => $p['sku'] . '-1',
                'sell_price_inc_tax' => $p['price'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
