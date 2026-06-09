<?php

namespace Tests\Feature\Sales;

use App\Domain\IAM\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesControllerTest extends TestCase
{
    use DatabaseTruncation;

    public function test_sales_controller_store_precision()
    {
        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Sales',
            'last_name' => 'Admin',
            'email' => 'sales' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Sales Corp',
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

        DB::table('product_stocks')->insert([
            ['product_id' => $productA, 'location_id' => $locationId, 'qty_available' => 100],
        ]);

        // Instead of HTTP, we can directly instantiate the controller to bypass routing issues.
        // Or if there is a route, use it. Since we don't know the exact route for SalesController@store,
        // we'll just instantiate the controller and call the method directly with a Request.
        $request = \Illuminate\Http\Request::create('/api/sales', 'POST', [
            'location_id' => $locationId,
            'transaction_date' => now()->toDateString(),
            'status' => 'final',
            'tax_rate' => 0.15,
            'discount_type' => 'percentage',
            'discount_amount' => 10,
            'items' => [
                ['product_id' => $productA, 'quantity' => 3, 'unit_price' => 19.99, 'discount' => 0],
            ]
        ]);
        
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Modules\Sales\Controllers\SalesController();
        $response = $controller->store($request);
        
        $data = json_decode($response->getContent(), true);
        if (!isset($data['transaction_id'])) {
            dd($data);
        }
        
        // Exact Math:
        // Subtotal = 3 * 19.99 = 59.97
        // Disc = 59.97 * 0.10 = 5.997
        // After disc = 53.973
        // Tax = 53.973 * 0.15 = 8.09595
        // Total = 53.973 + 8.09595 = 62.06895
        // Rounded total_before_tax = 53.9730
        // Rounded tax_amount = 8.0960
        // Rounded final_total = 62.0690

        $tx = DB::table('transactions')->where('id', $data['transaction_id'])->first();

        $this->assertSame('53.9730', $tx->total_before_tax);
        $this->assertSame('8.0960', $tx->tax_amount);
        $this->assertSame('62.0690', $tx->final_total);
    }
}
