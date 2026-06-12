<?php

namespace Tests\Feature\Mobile;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;

class MobileGatewayCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Mobile Tenant', 
            'created_at' => now(), 
            'updated_at' => now()
        ]);

        $this->user = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Mobile',
            'email' => 'admin@mobile.com',
            'password' => Hash::make('password123'),
            'business_id' => $this->businessId,
            'status' => 'active'
        ]);
    }

    public function test_hijacked_token_eviction()
    {
        // 1. Legitimate Login with Fingerprint Alpha
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'email' => 'admin@mobile.com',
            'password' => 'password123'
        ], [
            'X-Device-Fingerprint' => 'Device-Fingerprint-Alpha'
        ]);

        $response->assertStatus(200);
        $token = $response->json('token');

        // 2. Legitimate Request with same Fingerprint
        $validRequest = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Device-Fingerprint' => 'Device-Fingerprint-Alpha'
        ])->getJson('/api/v1/mobile/telemetry/pulse');

        $validRequest->assertStatus(200);

        // 3. Attack Request with hijacked token but different Fingerprint
        $attackRequest = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Device-Fingerprint' => 'Device-Fingerprint-Omega'
        ])->getJson('/api/v1/mobile/telemetry/pulse');

        $attackRequest->assertStatus(401);
        $attackRequest->assertJsonFragment([
            'error' => 'FPM Security: Device signature mismatch. Session revoked.'
        ]);

        // 4. Assert token was physically deleted from DB
        $this->assertEquals(0, DB::table('personal_access_tokens')->count());
    }

    public function test_payload_schema_structural_lock()
    {
        // Authenticate
        Sanctum::actingAs($this->user, ['mobile-access']);
        
        // We must manually create the token since Sanctum::actingAs doesn't set a real DB token for middleware
        $token = $this->user->createToken('Device-Fingerprint-Test', ['mobile-access']);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'X-Device-Fingerprint' => 'Device-Fingerprint-Test'
        ])->getJson('/api/v1/mobile/telemetry/pulse');

        $response->assertStatus(200);

        // Assert structural lock (lightweight keys only)
        $response->assertJsonStructure([
            'rev', 'drw', 'stk', 'ts'
        ]);

        // Assert heavy keys are completely absent
        $payload = $response->json();
        $this->assertArrayNotHasKey('net_revenue', $payload);
        $this->assertArrayNotHasKey('created_at', $payload);
    }
}
