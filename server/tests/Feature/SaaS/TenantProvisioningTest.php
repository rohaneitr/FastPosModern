<?php

namespace Tests\Feature\SaaS;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Services\TenantOnboardingService;
use App\Domain\IAM\Models\User;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup base roles for spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_atomic_tenant_provisioning_engine()
    {
        $service = new TenantOnboardingService();

        $payload = [
            'business_name' => 'Acme Corp',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@acme.com',
            'password' => 'secure123',
            'plan_id' => 'premium'
        ];

        $business = $service->onboardTenant($payload);

        $this->assertNotNull($business);

        // 1. Assert Business Created
        $this->assertDatabaseHas('businesses', ['name' => 'Acme Corp']);

        // 2. Assert Location
        $this->assertDatabaseHas('locations', ['business_id' => $business->id, 'is_default' => true]);

        // 3. Assert User & Roles
        $user = User::where('email', 'alice@acme.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($business->id, $user->business_id);
        $this->assertTrue($user->hasRole('BusinessAdmin'));

        // 4. Assert Financial Ledgers
        $this->assertDatabaseHas('accounts', ['business_id' => $business->id, 'type' => 'Cash']);
        $this->assertDatabaseHas('accounts', ['business_id' => $business->id, 'type' => 'Bank']);

        // 5. Assert Subscription created and valid for approx 30 days
        $sub = DB::table('saas_subscriptions')->where('business_id', $business->id)->first();
        $this->assertNotNull($sub);
        $this->assertEquals('premium', $sub->plan_id);
        $this->assertTrue(\Carbon\Carbon::parse($sub->valid_until)->isFuture());
    }

    public function test_cryptographic_webhook_and_idempotent_ledger()
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Stripe Inc', 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('saas_subscriptions')->insert([
            'business_id' => $businessId,
            'plan_id' => 'basic',
            'status' => 'Past_Due',
            'valid_until' => now()->subDays(5) // Expired 5 days ago
        ]);

        putenv('WEBHOOK_SECRET=my_super_secret');

        $payload = json_encode([
            'transaction_id' => 'TXN_999888',
            'business_id' => $businessId,
            'amount' => 50.00,
            'months_added' => 1
        ]);

        // Invalid signature
        $response1 = $this->withHeaders([
            'X-Signature' => 'invalid_hash'
        ])->postJson('/api/v1/webhooks/payment', json_decode($payload, true)); // Wait, postJson encodes array. 

        // Let's manually trigger the controller logic or build raw request
        $controller = new \App\Modules\Tenant\Controllers\SubscriptionWebhookController();
        
        $request = new \Illuminate\Http\Request();
        $request->setMethod('POST');
        $request->initialize([], [], [], [], [], ['HTTP_X_SIGNATURE' => 'bad'], $payload);
        
        $resp1 = $controller->handle($request);
        $this->assertEquals(401, $resp1->getStatusCode());

        // Valid signature
        $validSignature = hash_hmac('sha256', $payload, 'my_super_secret');
        $request2 = new \Illuminate\Http\Request();
        $request2->setMethod('POST');
        $request2->initialize([], [], [], [], [], ['HTTP_X_SIGNATURE' => $validSignature], $payload);
        
        $resp2 = $controller->handle($request2);
        $this->assertEquals(200, $resp2->getStatusCode());

        // Assert ledger updated and subscription extended
        $this->assertDatabaseHas('saas_payment_ledgers', ['transaction_id' => 'TXN_999888']);
        $subAfter = DB::table('saas_subscriptions')->where('business_id', $businessId)->first();
        $this->assertTrue(\Carbon\Carbon::parse($subAfter->valid_until)->isFuture());

        // Idempotency: Send EXACT same payload again
        $resp3 = $controller->handle($request2);
        $this->assertEquals(200, $resp3->getStatusCode()); // Gracefully ignores
        $this->assertEquals(1, DB::table('saas_payment_ledgers')->where('transaction_id', 'TXN_999888')->count());
    }

    public function test_402_kill_switch_middleware()
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Expired Corp', 'created_at' => now(), 'updated_at' => now()
        ]);

        $user = User::factory()->create(['business_id' => $businessId]);

        DB::table('saas_subscriptions')->insert([
            'business_id' => $businessId,
            'plan_id' => 'basic',
            'status' => 'Past_Due',
            'valid_until' => now()->subDay() // Expired yesterday
        ]);

        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Some API endpoint
        $request->server->set('REQUEST_URI', '/api/v1/inventory');

        $middleware = new \App\Http\Middleware\EnforceActiveSubscription();
        
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['data' => 'success']);
        });

        $this->assertEquals(402, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PAYMENT_REQUIRED', $data['error_code']);
    }
}
