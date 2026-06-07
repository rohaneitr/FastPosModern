<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HRTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = \App\Domain\Tenant\Models\Business::factory()->create([
            'name' => 'HR Business',
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

    public function test_can_list_employees()
    {
        $employeeId = DB::table('users')->insertGetId([
            'business_id' => $this->businessId,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'username' => 'janesmith',
            'email' => 'jane@example.com',
            'password' => 'pass',
            'allow_login' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('employee_profiles')->insert([
            'business_id' => $this->businessId,
            'user_id' => $employeeId,
            'designation' => 'Sales',
            'base_salary' => 5000,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/hr/employees');

        $response->assertStatus(200)
                 ->assertJsonFragment(['first_name' => 'Jane'])
                 ->assertJsonFragment(['designation' => 'Sales']);
    }

    public function test_can_list_payrolls()
    {
        $employeeId = DB::table('users')->insertGetId([
            'business_id' => $this->businessId,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'username' => 'janesmith2',
            'email' => 'jane2@example.com',
            'password' => 'pass',
            'allow_login' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('payrolls')->insert([
            'business_id' => $this->businessId,
            'user_id' => $employeeId,
            'reference_no' => 'PAY-2605-01',
            'month' => '2026-05',
            'base_salary' => 5000,
            'net_salary' => 4500,
            'payment_status' => 'paid',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/hr/payrolls');

        $response->assertStatus(200)
                 ->assertJsonPath('0.month', '2026-05')
                 ->assertJsonPath('0.base_salary', 5000)
                 ->assertJsonPath('0.net_salary', 4500);
    }
}
