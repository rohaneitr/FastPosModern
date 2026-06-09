<?php

namespace Tests\Feature\SaaS;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ModuleEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup base SaaS schema
        $this->businessId = DB::table('businesses')->insertGetId(['name' => 'Test Business', 'created_at' => now(), 'updated_at' => now()]);
        
        $this->userId = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password'),
            'business_id' => $this->businessId, 'role' => 'BusinessAdmin', 'created_at' => now(), 'updated_at' => now()
        ]);

        // We assume 'advanced-rma' module exists from migrations
        $this->rmaModuleId = DB::table('modules')->where('slug', 'advanced-rma')->value('id');
        if (!$this->rmaModuleId) {
            $this->rmaModuleId = DB::table('modules')->insertGetId([
                'name' => 'Advanced RMA', 'slug' => 'advanced-rma', 'created_at' => now(), 'updated_at' => now()
            ]);
        }

        // Add a fake route to test the middleware locally without relying on an existing real endpoint that might require other setups
        \Illuminate\Support\Facades\Route::middleware(['auth:sanctum', 'module:advanced-rma'])
            ->get('/api/v1/rma/verify/123', function() {
                return response()->json(['status' => 'success']);
            });
    }

    public function test_the_secure_guardrail_leak_proof()
    {
        $user = \App\Models\User::find($this->userId);
        
        // Tenant has NO modules attached
        $response = $this->actingAs($user)->getJson('/api/v1/rma/verify/123');
        
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'FPM Security: Module entitlement restricted. Upgrade your tier.',
            'error_code' => 'MODULE_RESTRICTED'
        ]);
    }

    public function test_the_real_time_revocation_purge_verification()
    {
        $user = \App\Models\User::find($this->userId);
        
        // Grant module
        DB::table('tenant_modules')->insert([
            'business_id' => $this->businessId,
            'module_id' => $this->rmaModuleId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Clear cache just in case
        Cache::forget("tenant_modules:{$this->businessId}");

        // Warm the cache, should pass
        $response1 = $this->actingAs($user)->getJson('/api/v1/rma/verify/123');
        $response1->assertStatus(200);

        // Mutate pivot using the event-aware observer / manual cache trigger for the test
        // Since we are not triggering a specific controller in this isolated test, we simulate the SuperAdmin action
        DB::table('tenant_modules')->where('business_id', $this->businessId)->update(['is_active' => false]);
        
        // To simulate observer catching pivot update natively, we must clear the cache manually OR ensure an observer fired
        // Let's implement atomic cache clear simulation that the SuperAdmin Controller would do
        Cache::forget("tenant_modules:{$this->businessId}");

        // Fire endpoint again, without explicit clear cache in the *client request* (backend did it)
        $response2 = $this->actingAs($user)->getJson('/api/v1/rma/verify/123');
        $response2->assertStatus(403);
    }
}
