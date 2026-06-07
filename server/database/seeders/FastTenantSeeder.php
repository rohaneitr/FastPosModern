<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Domain\IAM\Models\User;

class FastTenantSeeder extends Seeder
{
    public function run()
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        
        $owner = User::create([
            'business_id' => 0,
            'first_name' => 'Fast',
            'last_name' => 'Admin',
            'email' => 'admin@fast.localhost',
            'username' => 'fastadmin',
            'password' => Hash::make('Secret@12'),
            'user_type' => 'tenant',
            'allow_login' => true,
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Fast Subdomain Corp',
            'subdomain' => 'fast',
            'owner_id' => $owner->id,
            'is_active' => true,
            'language' => 'en',
            'time_zone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner->update(['business_id' => $businessId]);
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);
        $owner->assignRole($role);

        DB::statement('PRAGMA foreign_keys = ON;');
    }
}
