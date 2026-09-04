<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_html_responses_send_csp_report_only_without_unsafe_eval(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy-Report-Only');

        $policy = $response->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }
}
