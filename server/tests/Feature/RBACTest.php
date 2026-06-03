<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::table('businesses')->insert([
            ['id' => 1, 'name' => 'Biz A', 'is_active' => true],
        ]);
        DB::table('locations')->insert(['id' => 1, 'business_id' => 1, 'name' => 'Store']);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Basic', 'price' => 29, 'interval' => 'month']);
        DB::table('subscriptions')->insert(['business_id' => 1, 'plan_id' => 1, 'status' => 'active']);

        // Create roles and permissions
        Permission::firstOrCreate(['name' => 'products.manage']);
        Permission::firstOrCreate(['name' => 'inventory.manage']);
        Permission::firstOrCreate(['name' => 'sales.manage']);
        Permission::firstOrCreate(['name' => 'reports.manage']);
        Permission::firstOrCreate(['name' => 'pos.access']);
        Permission::firstOrCreate(['name' => 'tenant.manage']);
        Permission::firstOrCreate(['name' => 'users.manage']);

        $admin = Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $admin->syncPermissions(['tenant.manage', 'users.manage', 'products.manage', 'inventory.manage', 'sales.manage', 'reports.manage', 'pos.access']);

        $cashier = Role::firstOrCreate(['name' => 'Cashier']);
        $cashier->syncPermissions(['pos.access', 'sales.manage']);

        $invMgr = Role::firstOrCreate(['name' => 'InventoryManager']);
        $invMgr->syncPermissions(['products.manage', 'inventory.manage']);

        $accountant = Role::firstOrCreate(['name' => 'Accountant']);
        $accountant->syncPermissions(['reports.manage', 'sales.manage']);
    }

    public function test_business_admin_can_access_hr_routes()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('BusinessAdmin');

        $this->actingAs($user)
             ->getJson('/api/v1/hr/employees')
             ->assertSuccessful();
    }

    public function test_cashier_cannot_access_hr_routes()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('Cashier');

        $this->actingAs($user)
             ->getJson('/api/v1/hr/employees')
             ->assertStatus(403);
    }

    public function test_cashier_can_access_checkout()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('Cashier');

        // Should not get 403 — may get 422 due to missing payload, which is fine
        $response = $this->actingAs($user)
             ->postJson('/api/v1/checkout', []);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_cashier_cannot_access_inventory_adjust()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('Cashier');

        $this->actingAs($user)
             ->postJson('/api/v1/inventory/adjust', [])
             ->assertStatus(403);
    }

    public function test_inventory_manager_can_access_stock()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('InventoryManager');

        $this->actingAs($user)
             ->getJson('/api/v1/inventory/stock')
             ->assertSuccessful();
    }

    public function test_inventory_manager_cannot_access_expenses()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('InventoryManager');

        $this->actingAs($user)
             ->getJson('/api/v1/expenses')
             ->assertStatus(403);
    }

    public function test_accountant_can_access_reports()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('Accountant');

        $this->actingAs($user)
             ->getJson('/api/v1/reports/sales')
             ->assertSuccessful();
    }

    public function test_accountant_cannot_access_products()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        $user->assignRole('Accountant');

        $this->actingAs($user)
             ->getJson('/api/v1/products')
             ->assertStatus(403);
    }
}
