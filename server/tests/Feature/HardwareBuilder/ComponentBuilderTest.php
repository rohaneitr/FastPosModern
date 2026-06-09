<?php

namespace Tests\Feature\HardwareBuilder;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\HardwareBuilder\Services\BuilderValidationEngine;
use App\Modules\HardwareBuilder\Services\QuotationConversionService;
use App\Modules\HardwareBuilder\Exceptions\HardwareIncompatibilityException;
use Illuminate\Support\Facades\DB;

class ComponentBuilderTest extends TestCase
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
    }

    public function test_intel_amd_socket_conflict_enforcement()
    {
        $engine = new BuilderValidationEngine();

        $components = [
            [
                'category' => 'CPU',
                'attributes' => ['socket' => 'AM5']
            ],
            [
                'category' => 'Motherboard',
                'attributes' => ['socket_support' => ['LGA1700']] // Intel Socket
            ]
        ];

        $this->expectException(HardwareIncompatibilityException::class);
        
        try {
            $engine->validate($components);
        } catch (HardwareIncompatibilityException $e) {
            $response = $e->render(request());
            $this->assertEquals(422, $response->getStatusCode());
            $this->assertStringContainsString('socket_mismatch', json_encode($response->getData(true)));
            throw $e;
        }
    }

    public function test_price_conversion_decay_block()
    {
        $payload = json_encode([
            'components' => []
        ]);

        $quotationId = DB::table('commercial_quotations')->insertGetId([
            'business_id' => $this->businessId,
            'build_payload' => $payload,
            'total_price' => 1000.00,
            'valid_until' => now()->subDay(), // Expired 1 day ago
            'status' => 'Draft',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $engine = new BuilderValidationEngine();
        $conversionService = new QuotationConversionService($engine);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(410);
        $this->expectExceptionMessage("Quotation has expired.");

        $conversionService->convertToSale($quotationId);
    }

    public function test_defensive_stock_verification_gate()
    {
        // Create an active product with 0 stock
        $productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'RTX 4090',
            'sku' => 'GPU-4090',
            'stock_qty' => 0,
            'type' => 'single',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $payload = json_encode([
            'components' => [
                [
                    'product_id' => $productId,
                    'category' => 'GPU',
                    'sku' => 'GPU-4090',
                    'quantity' => 1,
                    'attributes' => []
                ]
            ]
        ]);

        $quotationId = DB::table('commercial_quotations')->insertGetId([
            'business_id' => $this->businessId,
            'build_payload' => $payload,
            'total_price' => 2000.00,
            'valid_until' => now()->addDays(5), // Valid
            'status' => 'Draft',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $engine = new BuilderValidationEngine();
        $conversionService = new QuotationConversionService($engine);

        $this->expectException(\App\Modules\HardwareBuilder\Exceptions\QuotationStockDeficitException::class);

        $conversionService->convertToSale($quotationId);
    }
}
