<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_send_hardening_headers(): void
    {
        $response = $this->get(route('welcome'))->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_content_security_policy_only_allows_known_script_and_connection_origins(): void
    {
        $policy = $this->get(route('welcome'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'unsafe-inline' 'unsafe-eval' https:\/\/challenges\.cloudflare\.com https:\/\/hcaptcha\.com https:\/\/\*\.hcaptcha\.com;/", $policy);
        $this->assertMatchesRegularExpression("/connect-src 'self' https:\/\/challenges\.cloudflare\.com [^;]*;/", $policy);
        $this->assertStringNotContainsString('connect-src *', $policy);
    }

    public function test_https_requests_enable_hsts(): void
    {
        $response = $this->get(str_replace('http://', 'https://', route('admin.login')))->assertOk();

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertStringEndsWith('upgrade-insecure-requests', $response->headers->get('Content-Security-Policy'));
    }
}
