<?php

namespace Tests\Feature\Sales;

use App\Domain\IAM\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialPrecisionTest extends TestCase
{
    use DatabaseTruncation;

    public function test_zero_error_financial_precision()
    {
        // 1. Setup Tenant Context
        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Financial',
            'last_name' => 'Admin',
            'email' => 'finance' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Precision Corp',
            'owner_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $ownerId)->update(['business_id' => $businessId]);

        $user = User::find($ownerId);

        $locationId = DB::table('locations')->insertGetId([
            'business_id' => $businessId,
            'name' => 'HQ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productA = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Product A',
            'sku' => 'PRD-A',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productB = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Product B',
            'sku' => 'PRD-B',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_stocks')->insert([
            ['product_id' => $productA, 'location_id' => $locationId, 'qty_available' => 100],
            ['product_id' => $productB, 'location_id' => $locationId, 'qty_available' => 100],
        ]);

        // 2. We deliberately construct a payload that triggers float precision loss if simple arithmetic is used.
        // E.g., 0.1 + 0.2 = 0.30000000000000004 in IEEE 754 floats.
        // Or prices that when multiplied and summed, cause subtle cent shifts.
        $payload = [
            'location_id' => $locationId,
            'tax_rate' => 0.0825, // 8.25%
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $productA, 'quantity' => 10, 'price' => 19.99],
                ['product_id' => $productB, 'quantity' => 5, 'price' => 1.01],
            ]
        ];

        // Exact math:
        // A: 10 * 19.99 = 199.90
        // B: 5 * 1.01 = 5.05
        // Subtotal = 204.95
        // Tax = 204.95 * 0.0825 = 16.908375
        // Total = 221.858375 -> rounded to 4 decimals = 221.8584
        
        $response = $this->actingAs($user)->withoutMiddleware()->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);
        
        $transactionId = $response->json('transaction_id');
        $tx = DB::table('transactions')->where('id', $transactionId)->first();

        // Check exact DB insertion up to 4 decimal places without float corruption
        $this->assertSame('204.9500', $tx->total_before_tax, 'Subtotal precision mismatch');
        $this->assertSame('16.9084', $tx->tax_amount, 'Tax precision mismatch');
        $this->assertSame('221.8584', $tx->final_total, 'Final total precision mismatch');
    }
}
