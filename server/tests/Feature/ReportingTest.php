<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Reporting Business',
            'owner_id' => 1,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'id' => 1,
            'business_id' => $this->businessId,
        ]);

        $this->locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Main Store',
        ]);
    }

    public function test_profit_loss_calculation()
    {
        // Insert Sales (Total: 500)
        DB::table('transactions')->insert([
            'business_id' => $this->businessId,
            'location_id' => $this->locationId,
            'created_by' => $this->user->id,
            'type' => 'sell',
            'status' => 'final',
            'final_total' => 500,
            'transaction_date' => Carbon::now(),
        ]);

        // Insert Purchases (Total: 200)
        DB::table('transactions')->insert([
            'business_id' => $this->businessId,
            'location_id' => $this->locationId,
            'created_by' => $this->user->id,
            'type' => 'purchase',
            'status' => 'received',
            'final_total' => 200,
            'transaction_date' => Carbon::now(),
        ]);

        // Insert Expense (Total: 100)
        DB::table('expenses')->insert([
            'business_id' => $this->businessId,
            'created_by' => $this->user->id,
            'total_amount' => 100,
            'expense_date' => Carbon::now(),
        ]);

        // Net Profit should be 500 - 200 - 100 = 200
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/reports/profit-loss');

        $response->assertStatus(200)
                 ->assertJson([
                     'total_sales' => 500,
                     'total_purchases' => 200,
                     'total_expenses' => 100,
                     'net_profit' => 200,
                 ]);
    }
}
