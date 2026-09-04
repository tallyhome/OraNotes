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

    #[Test]
    public function oversized_document_is_flagged(): void
    {
        $doc = OraDocument::empty();
        $doc['content'][0]['content'][0]['text'] = str_repeat('A', OraDocument::MAX_JSON_BYTES);

        $this->assertNotNull(OraDocument::limitError($doc));
    }

    #[Test]
    public function empty_document_is_within_limits(): void
    {
        $this->assertNull(OraDocument::limitError(OraDocument::empty()));
    }

    #[Test]
    public function valid_heading_list_and_table_documents_are_accepted(): void
    {
        $doc = [
            'version' => 1,
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Titre', 'marks' => [['type' => 'bold']]]]],
                ['type' => 'listItem', 'attrs' => ['level' => 0], 'content' => [['type' => 'text', 'text' => 'Tâche']]],
                [
                    'type' => 'table',
                    'content' => [
                        [
                            'type' => 'tableRow',
                            'content' => [
                                ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A']]]]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertTrue(OraDocument::isValid($doc));
        $this->assertNull(OraDocument::limitError($doc));
    }

    #[Test]
    public function unexpected_node_type_is_rejected(): void
    {
        $doc = OraDocument::empty();
        $doc['content'][] = ['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]];

        $this->assertNotNull(OraDocument::limitError($doc));
    }

    #[Test]
    public function associative_content_and_non_string_text_are_rejected(): void
    {
        $assoc = ['version' => 1, 'type' => 'doc', 'content' => ['oops' => ['type' => 'paragraph']]];
        $this->assertFalse(OraDocument::isValid($assoc));

        $badText = OraDocument::empty();
        $badText['content'][0]['content'][0]['text'] = ['not', 'a', 'string'];
        $this->assertNotNull(OraDocument::limitError($badText));
    }

    #[Test]
    public function deeply_nested_document_is_rejected(): void
    {
        $node = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]];
        for ($i = 0; $i < OraDocument::MAX_DEPTH + 2; $i++) {
            $node = ['type' => 'blockquote', 'content' => [$node]];
        }
        $doc = ['version' => 1, 'type' => 'doc', 'content' => [$node]];

        $this->assertNotNull(OraDocument::limitError($doc));
    }
}
