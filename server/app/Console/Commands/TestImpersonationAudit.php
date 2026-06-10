<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('test:impersonation-audit')]
#[Description('Test God-Mode Impersonation Audit Logging')]
class TestImpersonationAudit extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Simulating God-Mode Impersonation Request...");

        // 1. Ensure Roles Exist
        $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $businessAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin', 'guard_name' => 'web']);

        // 2. Find or Create a SuperAdmin
        $superAdmin = \App\Modules\IAM\Models\User::firstOrCreate(
            ['email' => 'sys@fastpos.com'],
            ['username' => 'sysadmin', 'first_name' => 'Sys', 'last_name' => 'Admin', 'password' => bcrypt('password')]
        );
        if (!$superAdmin->hasRole('SuperAdmin')) {
            $superAdmin->assignRole($superAdminRole);
        }

        if (!$superAdmin) {
            $this->error("No SuperAdmin found to test with.");
            return;
        }

        // 3. Find or Create a Tenant Admin
        $business = \App\Modules\Tenant\Models\Business::firstOrCreate(
            ['subdomain' => 'test-audit'],
            ['name' => 'Audit Test Business', 'owner_id' => $superAdmin->id]
        );

        $tenantAdmin = \App\Modules\IAM\Models\User::firstOrCreate(
            ['email' => 'audit_tenant@fastpos.com'],
            ['username' => 'audittenant', 'first_name' => 'Audit', 'last_name' => 'Tenant', 'password' => bcrypt('password'), 'business_id' => $business->id]
        );
        if (!$tenantAdmin->hasRole('BusinessAdmin')) {
            $tenantAdmin->assignRole($businessAdminRole);
        }

        if (!$tenantAdmin || !$tenantAdmin->business_id) {
            $this->error("No BusinessAdmin found to test with.");
            return;
        }

        // 4. Simulate the token with abilities
        $token = $tenantAdmin->createToken('impersonation_token', ['impersonate', 'admin_id:' . $superAdmin->id]);
        
        $tenantAdmin->impersonator_id = $superAdmin->id;

        // Mock authentication via the transient token
        auth()->guard('sanctum')->setUser(
            $token->accessToken->tokenable->withAccessToken($token->accessToken)
        );

        $this->info("Acting as: {$tenantAdmin->email} (Impersonated by SuperAdmin ID: {$superAdmin->id})");

        // 5. Perform a dummy action that triggers the AuditLogger
        $dummyId = rand(1000, 9999);
        \App\Modules\Tenant\Services\AuditLogger::tenantDeleted($tenantAdmin, "Dummy Impsersonation Test", $dummyId);

        // 6. Verify the log
        $log = \App\Modules\SuperAdmin\Models\AuditLog::latest('id')->first();

        if ($log && $log->impersonator_id === $superAdmin->id) {
            $this->info("✅ SUCCESS: Audit log accurately captured the Impersonator ID.");
            $this->line("Log Entry:");
            $this->line(json_encode($log->toArray(), JSON_PRETTY_PRINT));
        } else {
            $this->error("❌ FAILED: Audit log did not capture the correct Impersonator ID.");
            if ($log) {
                $this->line("Actual Log Entry:");
                $this->line(json_encode($log->toArray(), JSON_PRETTY_PRINT));
            }
        }
    }
}
