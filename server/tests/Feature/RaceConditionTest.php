<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class RaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = \App\Domain\Tenant\Models\Business::factory()->create([
            'name' => 'Race Condition Business',
            'owner_id' => 1,
            'is_active' => true,
        ])->id;

        $this->user = User::factory()->create([
            'id' => 1,
            'business_id' => $this->businessId,
            'allow_login' => true,
        ]);
        
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'BusinessAdmin']);
        $this->user->assignRole('BusinessAdmin');
        
        $this->locationId = DB::table('locations')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Race Store',
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'business_id' => $this->businessId, 
            'name' => 'Limited Edition Widget', 
            'type' => 'single', 
            'sku' => 'W-RACE-1', 
            'created_by' => $this->user->id, 
            'unit_id' => 1
        ]);

        // EXACTLY 1 IN STOCK
        DB::table('product_stocks')->insert([
            'product_id' => $this->productId, 
            'location_id' => $this->locationId, 
            'qty_available' => 1.0000,
        ]);
    }

    public function test_checkout_inventory_race_condition()
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $payload = [
            'location_id' => $this->locationId,
            'payment_method' => 'cash',
            'tax_rate' => 0,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1, 'price' => 100],
            ],
        ];

        $payloadJson = json_encode($payload);

        // We use artisan tinker to boot the app and run the request in parallel processes
        $phpCode = <<<PHP
\$request = \Illuminate\Http\Request::create('/api/v1/checkout', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer $token', 'HTTP_ACCEPT' => 'application/json'], '$payloadJson');
\$kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);
\$response = \$kernel->handle(\$request);
echo \$response->getStatusCode() . "|||" . \$response->getContent();
PHP;

        $process1 = new Process(['php', 'artisan', 'tinker', '--execute', $phpCode]);
        $process2 = new Process(['php', 'artisan', 'tinker', '--execute', $phpCode]);

        // Start both simultaneously
        $process1->start();
        $process2->start();

        // Wait for both to finish
        $process1->wait();
        $process2->wait();

        $status1 = trim($process1->getOutput());
        $status2 = trim($process2->getOutput());

        $successCount = 0;
        $failCount = 0;

        $parts1 = explode('|||', $status1);
        $parts2 = explode('|||', $status2);
        
        $code1 = $parts1[0] ?? '';
        $code2 = $parts2[0] ?? '';
        $body1 = $parts1[1] ?? '';
        $body2 = $parts2[1] ?? '';

        if ($code1 == '201') $successCount++;
        else $failCount++;

        if ($code2 == '201') $successCount++;
        else $failCount++;

        // Assert exactly one succeeded and one failed
        $this->assertEquals(1, $successCount, "Expected exactly 1 successful checkout, but got $successCount. Code 1: $code1, Code 2: $code2. Body 1: $body1 | Body 2: $body2");
        $this->assertEquals(1, $failCount, "Expected exactly 1 failed checkout, but got $failCount.");

        // The failed one should be 422 (validation error due to stock)
        $statuses = [$code1, $code2];
        $this->assertContains('422', $statuses, "Expected one request to return 422 Out of Stock, but got: " . implode(', ', $statuses));

        // Ensure stock never dropped below 0
        $stock = DB::table('product_stocks')->where('product_id', $this->productId)->first();
        $this->assertEquals(0, $stock->qty_available, "Stock should be exactly 0, but it is {$stock->qty_available} (possible negative inventory race condition)");
    }
}
