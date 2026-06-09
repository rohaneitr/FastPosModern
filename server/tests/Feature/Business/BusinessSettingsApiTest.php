<?php

namespace Tests\Feature\Business;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $cashierUser;
    protected $businessId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        \Spatie\Permission\Models\Role::create(['name' => 'BusinessAdmin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'Cashier', 'guard_name' => 'web']);

        // Create Admin User First
        $adminId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'email' => 'admin@settings.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Business
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Settings Test LLC',
            'owner_id' => $adminId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add active subscription to avoid 402 Payment Required
        DB::table('subscriptions')->insert([
            'business_id' => $this->businessId,
            'plan_id' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update Admin User
        DB::table('users')->where('id', $adminId)->update(['business_id' => $this->businessId]);
        $this->adminUser = User::find($adminId);
        $this->adminUser->assignRole('BusinessAdmin');

        // Create Cashier
        $cashierId = DB::table('users')->insertGetId([
            'first_name' => 'Cashier',
            'email' => 'cashier@settings.com',
            'password' => bcrypt('password'),
            'business_id' => $this->businessId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->cashierUser = User::find($cashierId);
        $this->cashierUser->assignRole('Cashier');
    }

    public function test_cashier_cannot_update_business_settings()
    {
        $response = $this->actingAs($this->cashierUser)->patchJson('/api/v1/business/settings', [
            'pos_enforce_device_lock' => false
        ]);

        $response->assertStatus(403);
    }

    public function test_business_admin_can_update_settings()
    {
        $response = $this->actingAs($this->adminUser)->patchJson('/api/v1/business/settings', [
            'pos_enforce_device_lock' => false,
            'pos_enforce_strict_cash_control' => false
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Business settings updated successfully',
            'settings' => [
                'pos_enforce_device_lock' => false,
                'pos_enforce_strict_cash_control' => false
            ]
        ]);

        $business = DB::table('businesses')->where('id', $this->businessId)->first();
        $settings = json_decode($business->settings, true);

        $this->assertFalse($settings['pos_enforce_device_lock']);
        $this->assertFalse($settings['pos_enforce_strict_cash_control']);
    }
}
