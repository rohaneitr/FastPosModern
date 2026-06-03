<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SuperadminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Superadmin User
        $this->user = User::factory()->create([
            'id' => 1,
            'email' => 'superadmin@example.com',
        ]);

        // Create a dummy business
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Dummy SaaS Tenant',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_can_list_businesses()
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/superadmin/businesses');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.business_name', 'Dummy SaaS Tenant')
                 ->assertJsonPath('data.0.is_active', 1);
    }

    public function test_can_toggle_business_status()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/superadmin/businesses/{$this->businessId}/toggle");

        $response->assertStatus(200)
                 ->assertJson(['is_active' => false]);

        $this->assertDatabaseHas('businesses', [
            'id' => $this->businessId,
            'is_active' => false,
        ]);
    }
}
