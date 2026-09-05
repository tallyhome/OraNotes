<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraEditorToolbarCssTest extends TestCase
{
    #[Test]
    public function test_vendored_toolbar_wraps_instead_of_scrolling_horizontally(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/vendor/ora-editor/ora-editor.css');

        $this->assertNotSame('', $css);
        $this->assertMatchesRegularExpression('/\.ora-toolbar\{[^}]*flex-wrap:wrap/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.ora-toolbar\{[^}]*flex-wrap:nowrap/', $css);
        $this->assertMatchesRegularExpression('/\.ora-toolbar-primary[^{]*\{display:contents/', $css);
        $this->assertMatchesRegularExpression('/\.ora-toolbar-menu\[hidden\][^{]*\{display:contents/', $css);
        $this->assertStringContainsString('.ora-toolbar-more-btn{display:none}', $css);
        $this->assertStringNotContainsString('overflow-x:auto', $css);
        $this->assertStringNotContainsString('1100px', $css);
    }

    #[Test]
    public function test_vendored_manifest_is_ora_editor_0_1_4(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/vendor/ora-editor/ora-editor.manifest.json'), true);
        $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/oranotes.php');

        $this->assertIsArray($manifest);
        $this->assertSame('0.1.4', $manifest['version']);
        $this->assertStringContainsString("'ora_editor_version' => '0.1.4'", $config);
    }
}
