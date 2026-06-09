<?php

namespace Tests\Feature\SerialCore;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Domain\Shared\Events\TransactionProcessing;
use App\Modules\SerialCore\Services\WarrantyManager;
use App\Modules\SerialCore\Exceptions\SerialAlreadyDepletedException;
use App\Modules\SerialCore\Exceptions\WarrantyExpiredException;

class SerialWarrantyTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Tech Retailer', 
            'created_at' => now(), 
            'updated_at' => now()
        ]);
        
        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'iPhone 15',
            'sku' => 'IP15',
            'type' => 'single',
            'is_serialized' => true,
            'warranty_months' => 36,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function test_duplicate_serial_checkout_block()
    {
        // Insert a serial that is already sold
        DB::table('inventory_item_serials')->insert([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'serial_number' => 'IMEI-999000',
            'status' => 'Sold',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $listener = new \App\Modules\SerialCore\Listeners\EnforceSerializedCheckout();

        $payload = [
            'business_id' => $this->businessId,
            'lines' => [
                [
                    'product_id' => $this->productId, 
                    'quantity' => 1, 
                    'is_serialized' => true, 
                    'serials' => ['IMEI-999000']
                ]
            ]
        ];

        $event = new TransactionProcessing($this->businessId, $payload['lines']);

        $this->expectException(SerialAlreadyDepletedException::class);
        $listener->handle($event);
    }

    public function test_expired_warranty_coverage_drop()
    {
        $transactionId = DB::table('transactions')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'paid',
            'transaction_date' => now()->subMonths(37)->toDateString() // Purchased 37 months ago
        ]);

        $sellLineId = DB::table('transaction_sell_lines')->insertGetId([
            'transaction_id' => $transactionId,
            'product_id' => $this->productId,
            'quantity' => 1,
            'unit_price_inc_tax' => 1000
        ]);

        DB::table('inventory_item_serials')->insert([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'transaction_sell_line_id' => $sellLineId,
            'serial_number' => 'IMEI-WARRANTY-TEST',
            'status' => 'Sold',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $manager = new WarrantyManager();

        $this->expectException(WarrantyExpiredException::class);
        $manager->verifyWarranty('IMEI-WARRANTY-TEST', $this->businessId);
    }

    public function test_serial_replacement_swap()
    {
        $transactionId = DB::table('transactions')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'paid',
            'transaction_date' => now()->subMonths(10)->toDateString() // Purchased 10 months ago
        ]);

        $sellLineId = DB::table('transaction_sell_lines')->insertGetId([
            'transaction_id' => $transactionId,
            'product_id' => $this->productId,
            'quantity' => 1,
            'unit_price_inc_tax' => 1000
        ]);

        // Old Defective Serial
        DB::table('inventory_item_serials')->insert([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'transaction_sell_line_id' => $sellLineId,
            'serial_number' => 'OLD-SERIAL-123',
            'status' => 'Sold',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // New Replacement Serial in Stock
        DB::table('inventory_item_serials')->insert([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'transaction_sell_line_id' => null,
            'serial_number' => 'NEW-SERIAL-456',
            'status' => 'In_Stock',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $manager = new WarrantyManager();
        $manager->swapReplacementSerial('OLD-SERIAL-123', 'NEW-SERIAL-456', $this->businessId);

        $old = DB::table('inventory_item_serials')->where('serial_number', 'OLD-SERIAL-123')->first();
        $new = DB::table('inventory_item_serials')->where('serial_number', 'NEW-SERIAL-456')->first();

        // Assert Old is Defective
        $this->assertEquals('Defective_Returned', $old->status);

        // Assert New is Sold and linked to the same historic line
        $this->assertEquals('Sold', $new->status);
        $this->assertEquals($sellLineId, $new->transaction_sell_line_id);
    }
}
