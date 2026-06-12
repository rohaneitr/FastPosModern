<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\Log;

class ProductionPerimeterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Prevent actual Redis cache from throwing errors in testing if missing
        Config::set('cache.default', 'array');
    }

    public function test_xss_and_sqli_interception_proof()
    {
        $user = User::factory()->create();

        // SQLi Test - Should block with 400
        $sqliPayload = [
            'name' => 'John Doe',
            'bio' => "admin' OR 1=1 --"
        ];

        $responseSqli = $this->actingAs($user)->putJson('/api/v1/profile', $sqliPayload);
        $responseSqli->assertStatus(400)
                     ->assertJson(['error_code' => 'SECURITY_SQLI_BLOCKED']);

        // XSS Test - Should strip malicious tags but allow request
        $xssPayload = [
            'name' => 'Jane <script>alert("hacked")</script> Doe',
            'preferences' => [
                'theme' => 'dark <iframe src="malicious"></iframe>'
            ]
        ];

        // The middleware strips it before validation. 
        // We'll hit a valid route and assert the payload it receives is clean.
        // We can just post to a mock route or the profile route.
        $responseXss = $this->actingAs($user)->putJson('/api/v1/profile', $xssPayload);
        
        // Either 200 or 422 depending on profile validation, but script tag MUST NOT exist
        // If 422, we assert the echoed data or DB doesn't have it.
        // If 200, we assert DB doesn't have it.
        // We can just inspect the request modification by building a simple mock route inside the test.
        
        \Illuminate\Support\Facades\Route::post('/_test_xss', function (\Illuminate\Http\Request $request) {
            return response()->json($request->all());
        })->middleware(\App\Http\Middleware\GlobalSecuritySanitizer::class);

        $mockResponse = $this->postJson('/_test_xss', $xssPayload);
        $mockResponse->assertStatus(200);
        $data = $mockResponse->json();

        $this->assertStringNotContainsString('<script>', $data['name']);
        $this->assertStringNotContainsString('<iframe>', $data['preferences']['theme']);
        $this->assertStringContainsString('Jane  Doe', $data['name']); // script stripped
    }

    public function test_429_auth_gateway_flood_block()
    {
        // Default limit is 5 per minute per IP
        // Test bypassing E2E headers
        $payload = ['email' => 'test@example.com', 'password' => 'password'];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/login', $payload);
            // Will fail auth with 401 or 422, but not 429
            $this->assertNotEquals(429, $response->status());
        }

        // 6th request should hit 429
        $response = $this->postJson('/api/v1/login', $payload);
        $response->assertStatus(429);
        $response->assertJson(['error_code' => 'RATE_LIMIT_EXCEEDED']);
    }

    public function test_backup_encryption_integrity()
    {
        Storage::fake('local');
        
        // Setup crypt key
        putenv('BACKUP_CRYPT_KEY=super-secret-key-32-bytes-long-!!');
        
        $exitCode = $this->artisan('backup:secure')->assertSuccessful();
        
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        
        $filePath = $files[0];
        $this->assertStringEndsWith('.enc', $filePath);
        
        $content = Storage::disk('local')->get($filePath);
        
        // Assert content is base64 encoded payload
        $this->assertNotFalse(base64_decode($content, true));
        
        // Assert plaintext is not visible
        $this->assertStringNotContainsString('FastPOS MySQL Dump', $content);
        
        // Assert we can decrypt it back
        $decoded = base64_decode($content);
        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);
        
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, $cipher, 'super-secret-key-32-bytes-long-!!', 0, $iv);
        $this->assertStringContainsString('FastPOS MySQL Dump', $decrypted);
    }
}
