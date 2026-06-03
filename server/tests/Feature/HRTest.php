<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HRTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'HR Business',
            'owner_id' => 1,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'id' => 1,
            'business_id' => $this->businessId,
        ]);
    }

    public function test_can_list_employees()
    {
        DB::table('employees')->insert([
            'business_id' => $this->businessId,
            'employee_id' => 'EMP-100',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'department' => 'Sales',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/hr/employees');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.first_name', 'Jane')
                 ->assertJsonPath('data.0.employee_id', 'EMP-100');
    }

    public function test_can_list_payrolls()
    {
        $employeeId = DB::table('employees')->insertGetId([
            'business_id' => $this->businessId,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('payrolls')->insert([
            'business_id' => $this->businessId,
            'employee_id' => $employeeId,
            'reference_no' => 'PAY-2605-01',
            'month' => '2026-05',
            'total_amount' => 5000,
            'payment_status' => 'paid',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/hr/payrolls');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.month', '2026-05')
                 ->assertJsonPath('data.0.total_amount', '5000.0000');
    }
}
