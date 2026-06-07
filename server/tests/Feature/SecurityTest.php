<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Cashier']);
    }

    public function test_tenant_isolation_breach_fails()
    {
        // Tenant 1
        $business1 = Business::factory()->create(['name' => 'Tenant 1', 'is_active' => true]);
        $user1 = User::factory()->create(['business_id' => $business1->id]);
        $user1->assignRole('BusinessAdmin');

        // Tenant 2
        $business2 = Business::factory()->create(['name' => 'Tenant 2', 'is_active' => true]);
        $user2 = User::factory()->create(['business_id' => $business2->id]);
        $user2->assignRole('BusinessAdmin');

        $location2 = DB::table('locations')->insertGetId([
            'business_id' => $business2->id,
            'name' => 'Tenant 2 Store',
        ]);

        // Create a transaction for Tenant 2
        $transactionId = DB::table('transactions')->insertGetId([
            'business_id' => $business2->id,
            'location_id' => $location2,
            'created_by' => $user2->id,
            'type' => 'sell',
            'status' => 'final',
            'invoice_no' => 'INV-TENANT2',
            'transaction_date' => Carbon::now(),
            'total_before_tax' => 100,
            'final_total' => 100,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Authenticate as User 1 (Tenant 1)
        $response = $this->actingAs($user1, 'sanctum')->getJson('/api/v1/sales/' . $transactionId);

        // Should be 404 (or 403) because the transaction belongs to Tenant 2
        $response->assertStatus(404);

        // Attempt to fetch list of sales passing Tenant 2's business_id
        $listResponse = $this->actingAs($user1, 'sanctum')->getJson('/api/v1/sales?business_id=' . $business2->id);
        
        $listResponse->assertStatus(200);
        // Assert the returned data DOES NOT contain Tenant 2's transaction
        $listResponse->assertJsonMissing(['invoice_no' => 'INV-TENANT2']);
    }

    public function test_privilege_escalation_fails()
    {
        $business = Business::factory()->create(['name' => 'Test Business', 'is_active' => true]);
        $cashier = User::factory()->create(['business_id' => $business->id]);
        $cashier->assignRole('Cashier');

        // Attempt to access reports (Admin only)
        $reportResponse = $this->actingAs($cashier, 'sanctum')->getJson('/api/v1/reports/profit-loss');
        
        $reportResponse->assertStatus(403);
    }
}
