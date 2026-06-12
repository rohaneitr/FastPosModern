<?php

namespace App\Modules\Tenant\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Modules\Tenant\Models\Business;
use App\Modules\IAM\Models\User;
use Spatie\Permission\Models\Role;

class TenantOnboardingService
{
    /**
     * Atomically provision a complete tenant workspace.
     * Rolls back completely if any sub-resource fails to insert.
     */
    public function onboardTenant(array $payload): Business
    {
        return DB::transaction(function () use ($payload) {
            // 1. Core Business Record
            $business = Business::create([
                'name' => $payload['business_name'],
                'email' => $payload['email'],
                'domain' => $payload['domain'] ?? null,
                'status' => 'active'
            ]);

            // 2. Default Location
            $locationId = DB::table('locations')->insertGetId([
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 3. Super Admin User for the Tenant
            $user = User::create([
                'business_id' => $business->id,
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'] ?? '',
                'email' => $payload['email'],
                'password' => Hash::make($payload['password']),
                'status' => 'active'
            ]);

            // 4. Role & Permission Bootstrapping
            // Verify roles exist, create if missing for this tenant context
            $adminRole = Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);
            $user->assignRole($adminRole);

            // 5. Core Financial Ledgers (Cash & Bank)
            DB::table('accounts')->insert([
                ['business_id' => $business->id, 'name' => 'Main Cash Drawer', 'type' => 'Cash', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['business_id' => $business->id, 'name' => 'Primary Bank Account', 'type' => 'Bank', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]
            ]);

            // 6. Bootstrap Initial Subscription
            DB::table('saas_subscriptions')->insert([
                'business_id' => $business->id,
                'plan_id' => $payload['plan_id'] ?? 'basic',
                'status' => 'Active',
                'valid_until' => now()->addMonth(), // 30-day initial window
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return $business;
        });
    }
}
