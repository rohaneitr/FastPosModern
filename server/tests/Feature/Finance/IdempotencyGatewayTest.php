<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class IdempotencyGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed base dependencies
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

    public function test_checkout_gateway_enforces_idempotency_key_and_returns_cached_response_preventing_duplicate_journal_lines()
    {
        $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();

        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]
            ],
            'payment_method' => 'cash',
            'amount_paid' => 100
        ];

        // Ensure database starts at 0 journal lines
        $this->assertDatabaseCount('journal_lines', 0);

        // Hit 1: The Initial Request
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', $payload, [
                'X-Idempotency-Key' => $idempotencyKey,
            ]);

        $response1->assertStatus(201);
        
        // After hit 1, checkout completes, journal lines are generated (e.g., 2 lines for debit/credit)
        $initialLineCount = \DB::table('journal_lines')->count();
        $this->assertTrue($initialLineCount > 0);

        // Hit 2: Concurrent/Retry Request with exactly the same key
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', $payload, [
                'X-Idempotency-Key' => $idempotencyKey,
            ]);

        // Should return the exact same 201 response payload without incrementing lines
        $response2->assertStatus(201);
        $this->assertEquals($response1->json(), $response2->json());

        // Hit 3: Another Retry
        $response3 = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', $payload, [
                'X-Idempotency-Key' => $idempotencyKey,
            ]);

        $response3->assertStatus(201);

        // Assert database count did NOT increment on hit 2 and 3
        $this->assertDatabaseCount('journal_lines', $initialLineCount);
    }
}
