<?php

namespace Tests\Feature\Analytics;

use Tests\TestCase;
use App\Models\User;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConsolidatedAnalyticsEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        
        $this->business = Business::factory()->create();
        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
        ]);
        $this->user->assignRole('BusinessAdmin');
        
        $this->product = Product::factory()->create([
            'business_id' => $this->business->id,
            'price' => 100,
        ]);
    }

    public function test_consolidated_multi_currency_sum()
    {
        Cache::flush();

        // 1. Force two sales in the database directly
        $transactionId1 = DB::table('transactions')->insertGetId([
            'business_id' => $this->business->id,
            'user_id' => $this->user->id,
            'location_id' => 1,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 10000.0000,
            'tax_amount' => 0.0000,
            'currency_code' => 'BDT',
            'exchange_rate_used' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transactionId2 = DB::table('transactions')->insertGetId([
            'business_id' => $this->business->id,
            'user_id' => $this->user->id,
            'location_id' => 1,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100.0000,
            'tax_amount' => 0.0000,
            'currency_code' => 'USD',
            'exchange_rate_used' => 115.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Hit the API
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/overview');

        $response->assertStatus(200);
        
        // 10000.0000 * 1.0 + 100.0000 * 115.0000 = 10000 + 11500 = 21500.0000
        $this->assertEquals('21500.0000', $response->json('metrics.total_revenue'));
    }

    public function test_cache_hit_isolation()
    {
        Cache::flush();

        DB::table('transactions')->insertGetId([
            'business_id' => $this->business->id,
            'user_id' => $this->user->id,
            'location_id' => 1,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 5000.0000,
            'tax_amount' => 0.0000,
            'currency_code' => 'BDT',
            'exchange_rate_used' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Hit 1: Warm Cache
        $response1 = $this->actingAs($this->user)->getJson('/api/v1/analytics/overview');
        $this->assertEquals('5000.0000', $response1->json('metrics.total_revenue'));

        // Modify raw DB row
        DB::table('transactions')->update(['total_amount' => 9999.0000]);

        // Hit 2: Cache Hit
        $response2 = $this->actingAs($this->user)->getJson('/api/v1/analytics/overview');
        $this->assertEquals('5000.0000', $response2->json('metrics.total_revenue')); // Should remain 5000.0000
    }
}
