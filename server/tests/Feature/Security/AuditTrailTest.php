<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;
use App\Domain\IAM\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;

class AuditTrailTest extends TestCase
{
    public function test_adjust_stock_creates_forensic_audit_trail_with_exact_strings()
    {
        // Setup Tenant & User
        $business = Business::first();
        $user = User::where('business_id', $business->id)->first();
        $location = DB::table('locations')->where('business_id', $business->id)->first();
        
        $this->actingAs($user);

        // Setup Product
        $productId = DB::table('products')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Audit Trail Test Product',
            'sku' => 'AT-SKU-' . time(),
            'purchase_price' => 10,
            'selling_price' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stockId = DB::table('product_stocks')->insertGetId([
            'product_id' => $productId,
            'location_id' => $location->id,
            'qty_available' => '100.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Action: Adjust Stock (-5.5555)
        $action = new AdjustStockAction();
        $action->execute($business->id, $user->id, $productId, $location->id, -5.5555, 'Test Audit');

        // Verify Audit Log
        $log = DB::table('audit_logs')
            ->where('subject_type', 'ProductStock')
            ->where('subject_id', $stockId)
            ->where('action', 'adjust_stock')
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($log, "Audit log was not created for stock adjustment.");

        $beforeState = json_decode($log->before_state, true);
        $afterState = json_decode($log->after_state, true);

        // Assertion: Values match the exact BigDecimal strings (no floating point truncation)
        $this->assertEquals('100.0000', $beforeState['qty_available']);
        $this->assertEquals('94.4445', $afterState['qty_available']); // 100 - 5.5555

        // Assertion: Tamper Evident Hash exists
        $this->assertNotNull($log->checksum);
        $this->assertEquals(64, strlen($log->checksum)); // SHA-256 length
    }
}
