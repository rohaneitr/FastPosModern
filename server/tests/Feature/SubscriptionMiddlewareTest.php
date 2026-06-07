<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('users')->insert([
            'id' => 1, 'first_name' => 'Test', 'last_name' => 'User', 'username' => 'testowner', 'email' => 'test@owner.com', 'password' => 'pass', 'allow_login' => 1, 'user_type' => 'user', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('businesses')->insert([
            ['id' => 1, 'name' => 'Active Biz', 'is_active' => true, 'subscription_status' => 'Active', 'subscription_ends_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now(), 'owner_id' => 1],
            ['id' => 2, 'name' => 'Expired Biz', 'is_active' => true, 'subscription_status' => 'Expired', 'subscription_ends_at' => now()->subDays(1), 'created_at' => now(), 'updated_at' => now(), 'owner_id' => 1],
            ['id' => 3, 'name' => 'Suspended Biz', 'is_active' => false, 'subscription_status' => 'Suspended', 'subscription_ends_at' => now()->subDays(1), 'created_at' => now(), 'updated_at' => now(), 'owner_id' => 1],
        ]);
        DB::table('plans')->updateOrInsert(['id' => 1], ['name' => 'Basic', 'price' => 29, 'interval' => 'month']);
        
        // Active subscription
        DB::table('subscriptions')->updateOrInsert(['business_id' => 1], ['plan_id' => 1, 'status' => 'active']);
        // Cancelled subscription
        DB::table('subscriptions')->updateOrInsert(['business_id' => 2], ['plan_id' => 1, 'status' => 'cancelled']);
    }

    public function test_active_subscription_allows_access()
    {
        $user = User::factory()->create(['business_id' => 1, 'allow_login' => true]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $user->assignRole('BusinessAdmin');

        $this->actingAs($user)
             ->getJson('/api/v1/products')
             ->assertSuccessful();
    }

    public function test_cancelled_subscription_returns_402()
    {
        $user = User::factory()->create(['business_id' => 2, 'allow_login' => true]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $user->assignRole('BusinessAdmin');

        $this->actingAs($user)
             ->getJson('/api/v1/products')
             ->assertStatus(402);
    }

    public function test_suspended_business_returns_403()
    {
        $user = User::factory()->create(['business_id' => 3, 'allow_login' => true]);

        $this->actingAs($user)
             ->getJson('/api/v1/products')
             ->assertStatus(403);
    }

    public function test_no_subscription_returns_402()
    {
        // Business 3 has no subscription and is suspended
        // Create business 4 with no subscription but active
        \Illuminate\Support\Facades\DB::table('businesses')->insert(['id' => 4, 'name' => 'No Sub Biz', 'is_active' => true, 'subscription_status' => 'Expired', 'created_at' => now(), 'updated_at' => now(), 'owner_id' => 1]);
        $user = User::factory()->create(['business_id' => 4, 'allow_login' => true]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $user->assignRole('BusinessAdmin');

        $this->actingAs($user)
             ->getJson('/api/v1/products')
             ->assertStatus(402)
             ->assertJsonPath('error_code', 'SUBSCRIPTION_EXPIRED');
    }
}
