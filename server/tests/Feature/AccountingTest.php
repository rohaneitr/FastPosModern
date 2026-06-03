<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Accounting Business',
            'owner_id' => 1,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'id' => 1,
            'business_id' => $this->businessId,
        ]);
    }

    public function test_can_log_expense()
    {
        $payload = [
            'total_amount' => 1500,
            'expense_date' => Carbon::now()->format('Y-m-d H:i:s'),
            'note' => 'Office Rent',
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/expenses', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Expense logged successfully');

        $this->assertDatabaseHas('expenses', [
            'business_id' => $this->businessId,
            'total_amount' => 1500,
            'note' => 'Office Rent',
        ]);
    }

    public function test_can_list_expenses()
    {
        DB::table('expenses')->insert([
            'business_id' => $this->businessId,
            'created_by' => $this->user->id,
            'reference_no' => 'EXP-001',
            'total_amount' => 300,
            'expense_date' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/expenses');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.reference_no', 'EXP-001')
                 ->assertJsonPath('data.0.total_amount', '300.0000');
    }
}
