<?php

namespace Tests\Feature\Security;

use App\Domain\IAM\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RBACEnforcementTest extends TestCase
{
    use DatabaseTruncation;

    public function test_cashier_cannot_delete_category()
    {
        // 1. Setup Tenant and Users
        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Admin',
            'last_name' => 'Owner',
            'email' => 'admin_owner_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'RBAC Corp',
            'owner_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cashierId = DB::table('users')->insertGetId([
            'first_name' => 'Cashier',
            'last_name' => 'User',
            'email' => 'cashier@example.com',
            'password' => bcrypt('password'),
            'business_id' => $businessId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Spatie Roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        
        $cashier = User::find($cashierId);
        $cashier->assignRole('Cashier');

        // 2. Setup Resource
        $categoryId = DB::table('categories')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Test Category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Attempt Delete as Cashier
        // Note: The route is registered as part of apiResource inside the Cashier middleware group
        $response = $this->actingAs($cashier)
                         ->withoutMiddleware([\App\Http\Middleware\CheckSubscription::class])
                         ->deleteJson("/api/v1/categories/{$categoryId}");

        // Assert that the system blocks the deletion with a 403 Forbidden
        $response->assertStatus(403);
        
        // Assert category still exists
        $this->assertDatabaseHas('categories', ['id' => $categoryId]);
    }
}
