<?php

namespace Tests\Feature\Pharmacy;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Domain\Shared\Events\TransactionProcessing;
use App\Modules\Pharmacy\Exceptions\ExpiredStockException;

class PharmacyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = DB::table('businesses')->insertGetId(['name' => 'PharmCorp', 'created_at' => now(), 'updated_at' => now()]);
        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Paracetamol',
            'sku' => 'PARA100',
            'type' => 'single',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Register the provider
        app()->register(\App\Modules\Pharmacy\Providers\PharmacyServiceProvider::class);
    }

    public function test_fefo_deduction_proof()
    {
        // Batch A: Expiring in 10 days
        $batchA = DB::table('pharmacy_batches')->insertGetId([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'batch_number' => 'BATCH-A',
            'quantity_available' => 50,
            'expiry_date' => now()->addDays(10)->toDateString(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Batch B: Expiring in 60 days
        $batchB = DB::table('pharmacy_batches')->insertGetId([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'batch_number' => 'BATCH-B',
            'quantity_available' => 100,
            'expiry_date' => now()->addDays(60)->toDateString(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Simulate sale of 20 units
        $payload = [
            'business_id' => $this->businessId,
            'lines' => [
                ['product_id' => $this->productId, 'quantity' => 20]
            ]
        ];

        event(new TransactionProcessing($this->businessId, $payload['lines']));

        // Assert Batch A (10 days) was deducted
        $this->assertEquals(30, DB::table('pharmacy_batches')->where('id', $batchA)->value('quantity_available'));
        
        // Assert Batch B (60 days) remains untouched
        $this->assertEquals(100, DB::table('pharmacy_batches')->where('id', $batchB)->value('quantity_available'));
    }

    public function test_hard_expiry_blockade()
    {
        // Batch Expired 5 days ago
        DB::table('pharmacy_batches')->insertGetId([
            'business_id' => $this->businessId,
            'product_id' => $this->productId,
            'batch_number' => 'BATCH-EXPIRED',
            'quantity_available' => 50,
            'expiry_date' => now()->subDays(5)->toDateString(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->expectException(ExpiredStockException::class);
        $this->expectExceptionMessage('Cannot dispense expired medication from Batch BATCH-EXPIRED');

        $payload = [
            'business_id' => $this->businessId,
            'lines' => [
                ['product_id' => $this->productId, 'quantity' => 10]
            ]
        ];

        event(new TransactionProcessing($this->businessId, $payload['lines']));
    }
}
