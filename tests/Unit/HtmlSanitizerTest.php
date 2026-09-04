<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    #[Test]
    public function test_keeps_safe_markup(): void
    {
        $clean = HtmlSanitizer::clean('<p>ok <strong>gras</strong></p>');

        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('ok', $clean);
        $this->assertStringContainsString('<strong>gras</strong>', $clean);
    }

    #[Test]
    public function test_strips_script_elements_and_contents(): void
    {
        $clean = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>ok</p>', $clean);
        $this->assertStringNotContainsString('script', strtolower($clean));
        $this->assertStringNotContainsString('alert(1)', $clean);
    }

    #[Test]
    #[DataProvider('dangerousMarkupProvider')]
    public function test_neutralizes_event_handlers_and_unsafe_urls(string $input): void
    {
        $clean = strtolower(HtmlSanitizer::clean($input));

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('data:', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('vbscript:', $clean);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dangerousMarkupProvider(): array
    {
        return [
            'spaced_onerror' => ['<img src=x onerror=alert(1)>'],
            'no_space_onerror' => ['<img src="x"onerror="alert(1)">'],
            'uppercase' => ['<IMG SRC=x OnErRoR=alert(1)>'],
            'javascript_href' => ['<a href="javascript:alert(1)">x</a>'],
            'javascript_encoded' => ['<a href="java&#115;cript:alert(1)">x</a>'],
            'data_uri' => ['<a href="data:text/html,alert(1)">x</a>'],
            'style_attr' => ['<p style="background:url(javascript:alert(1))">ok</p>'],
            'svg' => ['<svg onload=alert(1)><script>alert(1)</script></svg>'],
            'iframe' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'],
        ];
    }

    #[Test]
    public function test_allows_http_links_and_forces_noopener_on_blank(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://example.test" target="_blank">ok</a>');

        $this->assertStringContainsString('https://example.test', $clean);
        $this->assertStringContainsString('noopener', $clean);
    }

    #[Test]
    public function test_rejects_protocol_relative_urls_as_unsafe(): void
    {
        $this->assertFalse(HtmlSanitizer::isSafeUrl('//evil.test/x'));
        $this->assertTrue(HtmlSanitizer::isSafeUrl('https://example.test/a'));
        $this->assertTrue(HtmlSanitizer::isSafeUrl('/a/uuid-here', forImage: true));
        $this->assertFalse(HtmlSanitizer::isSafeUrl('javascript:alert(1)'));
    }
}
