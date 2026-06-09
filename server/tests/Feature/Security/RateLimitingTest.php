<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Modules\Tenant\Models\Business;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter cache before tests
        RateLimiter::clear('api');
        RateLimiter::clear('sensitive');
        Cache::store('redis')->flush();
    }

    public function test_standard_api_rate_limiter_blocks_excessive_requests()
    {
        $endpoint = '/api/v1/health'; // Public API endpoint

        // Standard tier is 60 requests per minute
        for ($i = 1; $i <= 60; $i++) {
            $response = $this->get($endpoint);
            // It might return 200 or 403, we don't care, we just want to ensure it's not 429 yet
            $this->assertNotEquals(429, $response->status(), "Request $i was rate limited too early.");
        }

        // The 61st request should be blocked
        $response = $this->get($endpoint);
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_sensitive_endpoint_rate_limiter_blocks_brute_force()
    {
        $endpoint = '/api/v1/login';

        // Sensitive tier is 5 requests per minute
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson($endpoint, [
                'email' => 'wrong@example.com',
                'password' => 'wrongpass'
            ]);
            $this->assertNotEquals(429, $response->status(), "Sensitive Request $i was rate limited too early.");
        }

        // The 61st request should be blocked (Wait, 6th request for sensitive!)
        $response = $this->postJson($endpoint, [
            'email' => 'wrong@example.com',
            'password' => 'wrongpass'
        ]);
        
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_e2e_bypass_header_prevents_rate_limiting()
    {
        $endpoint = '/api/v1/login';

        // Even after 10 requests, it should not 429 if the bypass header is present
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->withHeaders([
                'X-E2E-Bypass' => 'true'
            ])->postJson($endpoint, [
                'email' => 'wrong@example.com',
                'password' => 'wrongpass'
            ]);
            $this->assertNotEquals(429, $response->status(), "Bypass Request $i was rate limited.");
        }
    }
}
