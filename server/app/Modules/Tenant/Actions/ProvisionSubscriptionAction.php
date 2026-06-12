<?php

namespace App\Modules\Tenant\Actions;

use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Business;
use App\Modules\Tenant\Models\Subscription;

class ProvisionSubscriptionAction
{
    public function execute(int $businessId, Plan $plan): void
    {
        // 1. Create the subscription
        DB::table('subscriptions')->insert([
            'business_id' => $businessId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Sync the modules
        $enabledModules = $plan->enabled_modules ?? [];
        if (!is_array($enabledModules)) {
            $enabledModules = json_decode($enabledModules, true) ?? [];
        }

        // Add 'core_pos' to all by default if not present
        if (!in_array('core_pos', $enabledModules)) {
            $enabledModules[] = 'core_pos';
        }

        DB::table('businesses')->where('id', $businessId)->update([
            'active_modules' => json_encode($enabledModules),
            'subscription_status' => 'Active',
            'subscription_ends_at' => $plan->interval === 'year' ? now()->addYear() : now()->addMonth(),
        ]);

        // 3. Sync the tenant_modules pivot
        $registry = config('fpm_modules') ?? [];
        $moduleIds = [];

        foreach ($enabledModules as $slug) {
            if (isset($registry[$slug])) {
                $module = DB::table('modules')->where('slug', $slug)->first();
                if (!$module) {
                    $moduleId = DB::table('modules')->insertGetId([
                        'name' => $registry[$slug]['name'],
                        'slug' => $slug,
                        'description' => $registry[$slug]['description'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $moduleIds[] = $moduleId;
                } else {
                    $moduleIds[] = $module->id;
                }
            }
        }

        DB::table('tenant_modules')->where('business_id', $businessId)->delete();
        $pivotInserts = array_map(function($modId) use ($businessId) {
            return [
                'business_id' => $businessId,
                'module_id' => $modId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $moduleIds);
        
        if (!empty($pivotInserts)) {
            DB::table('tenant_modules')->insert($pivotInserts);
        }
        
        // 4. Update Spatie Permissions for BusinessAdmin
        $owner = \App\Modules\IAM\Models\User::where('business_id', $businessId)
                    ->whereHas('roles', function($q) { $q->where('name', 'BusinessAdmin'); })
                    ->first();
                    
        if ($owner) {
            $permissionsToSync = [];
            foreach ($enabledModules as $slug) {
                $permName = "module.{$slug}";
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'sanctum']);
                $permissionsToSync[] = $permName;
            }
            $owner->syncPermissions($permissionsToSync);
        }
        
        // 5. Default business settings seed
        // Setting up standard currency, timezone, defaults
        DB::table('settings')->insertOrIgnore([
            ['business_id' => $businessId, 'key' => 'currency', 'value' => 'USD', 'group' => 'general'],
            ['business_id' => $businessId, 'key' => 'timezone', 'value' => 'UTC', 'group' => 'general'],
            ['business_id' => $businessId, 'key' => 'tax_rate', 'value' => '0', 'group' => 'financial'],
        ]);
    }
}
