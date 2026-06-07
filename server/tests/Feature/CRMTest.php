<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;

class CRMTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Scaffold minimal required tenant data
        $this->businessId = \App\Domain\Tenant\Models\Business::factory()->create([
            'name' => 'Test Business',
            'owner_id' => 1,
            'is_active' => true,
        ])->id;

        $this->user = User::factory()->create([
            'id' => 1,
            'business_id' => $this->businessId,
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $this->user->assignRole('BusinessAdmin');
    }

    public function test_can_list_contacts()
    {
        DB::table('contacts')->insert([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/contacts');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_can_create_contact()
    {
        $payload = [
            'type' => 'supplier',
            'first_name' => 'Acme',
            'supplier_business_name' => 'Acme Corp',
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/contacts', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Acme');

        $this->assertDatabaseHas('contacts', [
            'type' => 'supplier',
            'name' => 'Acme',
            'business_id' => $this->businessId
        ]);
    }
}
