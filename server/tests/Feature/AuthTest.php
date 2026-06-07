<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('users')->insert([
            'id' => 1, 'first_name' => 'Test', 'last_name' => 'User', 'username' => 'testowner', 'email' => 'test@owner.com', 'password' => 'pass', 'allow_login' => 1, 'user_type' => 'user', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('businesses')->insert(
            ['id' => 1, 'name' => 'Test Biz', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'owner_id' => 1]
        );
        DB::table('plans')->updateOrInsert(['id' => 1], ['name' => 'Basic', 'price' => 29, 'interval' => 'month']);
        DB::table('subscriptions')->updateOrInsert(['business_id' => 1], ['plan_id' => 1, 'status' => 'active']);
    }

    public function test_login_with_valid_credentials_returns_token()
    {
        $user = User::factory()->create([
            'business_id' => 1,
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => Hash::make('Secret@123'),
            'allow_login' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => 'Secret@123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_login_with_wrong_password_returns_422()
    {
        User::factory()->create([
            'business_id' => 1,
            'username' => 'testuser',
            'password' => Hash::make('Secret@123'),
            'allow_login' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_disabled_user_cannot_login()
    {
        User::factory()->create([
            'business_id' => 1,
            'username' => 'disabled',
            'password' => Hash::make('Secret@123'),
            'allow_login' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'disabled',
            'password' => 'Secret@123',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_token()
    {
        $user = User::factory()->create([
            'business_id' => 1,
            'allow_login' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Logout
        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/logout');
        $response->assertStatus(200);

        // Verify token deleted from database directly
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_forgot_password_generates_token()
    {
        User::factory()->create([
            'business_id' => 1,
            'email' => 'forgot@example.com',
        ]);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'forgot@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'forgot@example.com',
        ]);
    }

    public function test_reset_password_with_valid_token()
    {
        $user = User::factory()->create([
            'business_id' => 1,
            'email' => 'reset@example.com',
            'password' => Hash::make('OldPassword@1'),
        ]);

        // Manually insert reset token
        $token = 'test-token-123456789012345678901234567890';
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Reset password
        $response = $this->postJson('/api/v1/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'N3wP@ssw0rd!FastPOS2026',
            'password_confirmation' => 'N3wP@ssw0rd!FastPOS2026',
        ]);

        $response->assertStatus(200);

        // Verify new password works
        $this->postJson('/api/v1/login', [
            'username' => 'reset@example.com',
            'password' => 'N3wP@ssw0rd!FastPOS2026',
        ])->assertStatus(200);
    }
}
