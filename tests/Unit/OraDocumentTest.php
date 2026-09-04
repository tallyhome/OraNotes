<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use App\Support\OraDocument;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OraDocumentTest extends TestCase
{
    #[Test]
    public function empty_document_is_valid_v1(): void
    {
        $doc = OraDocument::empty();

        $this->assertTrue(OraDocument::isValid($doc));
        $this->assertSame(1, $doc['version']);
        $this->assertSame('doc', $doc['type']);
    }

    #[Test]
    public function extract_text_walks_nodes(): void
    {
        $doc = OraDocument::empty();
        $doc['content'][0]['content'][0]['text'] = 'Bonjour le bureau';

        $this->assertSame('Bonjour le bureau', OraDocument::extractText($doc));
    }

    #[Test]
    public function html_sanitizer_strips_scripts(): void
    {
        $clean = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>');

        $this->assertStringContainsString('<p>ok</p>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
    }

    #[Test]
    public function html_sanitizer_strips_style_attributes(): void
    {
        $clean = HtmlSanitizer::clean('<p style="background:url(javascript:alert(1))">ok</p>');

        $this->assertStringContainsString('<p', $clean);
        $this->assertStringContainsString('ok', $clean);
        $this->assertStringNotContainsString('style', $clean);
    }
}
