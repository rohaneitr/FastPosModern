<?php

namespace Tests\Feature\Purchases;

use App\Domain\IAM\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseFinancialPrecisionTest extends TestCase
{
    use DatabaseTruncation;

    public function test_purchase_landed_cost_precision()
    {
        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Purchase',
            'last_name' => 'Admin',
            'email' => 'purchase' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Purchasing Corp',
            'owner_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $ownerId)->update(['business_id' => $businessId]);

        $contactId = DB::table('contacts')->insertGetId([
            'business_id' => $businessId,
            'type' => 'supplier',
            'name' => 'Global Supplier Inc.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productA = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Component A',
            'sku' => 'COMP-A',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productB = DB::table('products')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Component B',
            'sku' => 'COMP-B',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($ownerId);

        $payload = [
            'contact_id' => $contactId,
            'purchase_date' => now()->toDateString(),
            'status' => 'received',
            'tax_rate' => 0.075, // 7.5% tax
            'discount_amount' => 50.00,
            'shipping_charges' => 125.50,
            'lines' => [
                ['product_id' => $productA, 'quantity' => 10, 'purchase_price' => 14.99],
                ['product_id' => $productB, 'quantity' => 5, 'purchase_price' => 99.95],
            ]
        ];

        \Illuminate\Support\Facades\Route::middleware('web')->post('/api/purchases', [\App\Modules\Procurement\Controllers\PurchaseController::class, 'store']);
        
        $response = $this->actingAs($user)->postJson('/api/purchases', $payload);
        
        $data = $response->json();

        if (!isset($data['data']['id'])) {
            dd($data);
        }

        $purchaseId = $data['data']['id'];
        $purchase = DB::table('purchases')->where('id', $purchaseId)->first();

        // Exact Math Calculation using BigDecimal standard (SCALE 4, HALF_UP):
        // Line A: 10 * 14.99 = 149.90
        // Line B: 5 * 99.95 = 499.75
        // Subtotal = 649.65
        // Discount = 50.00
        // After Discount = 599.65
        // Tax (7.5%) = 599.65 * 0.075 = 44.97375 -> rounded to 4 scale = 44.9738
        // Shipping = 125.50
        // Grand Total = 599.65 + 44.9738 + 125.50 = 770.1238

        $this->assertSame('649.6500', $purchase->total_before_tax, 'Subtotal precision mismatch');
        $this->assertSame('50.0000', $purchase->discount_amount, 'Discount precision mismatch');
        $this->assertSame('44.9738', $purchase->tax_amount, 'Tax precision mismatch');
        $this->assertSame('125.5000', $purchase->shipping_charges, 'Shipping precision mismatch');
        $this->assertSame('770.1238', $purchase->grand_total, 'Grand Total precision mismatch');

        // Verify supplier ledger
        $ledger = DB::table('supplier_ledgers')->where('purchase_id', $purchaseId)->first();
        $this->assertNotNull($ledger, 'Supplier ledger was not created');
        $this->assertSame('770.1238', $ledger->amount, 'Supplier ledger amount precision mismatch');
    }
}
