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
        
        $superAdminUser = \App\Modules\IAM\Models\User::find($superAdminId);
        $superAdminUser->assignRole('SuperAdmin');


    }
}
