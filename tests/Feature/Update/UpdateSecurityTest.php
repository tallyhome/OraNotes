<?php

namespace Tests\Feature\Update;

use App\Services\Update\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_arbitrary_download_hosts_are_rejected(): void
    {
        $service = app(UpdateService::class);

        try {
            $service->assertOfficialDownload('https://evil.example/payload.zip');
            $this->fail('Expected rejection');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    #[Test]
    public function test_downgrade_is_refused(): void
    {
        config(['oranotes.version' => '1.1.0']);
        $compat = app(UpdateService::class)->compatibility('1.0.0');

        $this->assertFalse($compat['ok']);
        $this->assertNotEmpty($compat['errors']);
    }

    #[Test]
    public function test_status_uses_configured_github_api_only(): void
    {
        Http::fake([
            'https://api.github.com/repos/tallyhome/OraNotes/releases/latest' => Http::response([
                'tag_name' => 'v1.2.0',
                'prerelease' => false,
                'body' => 'Notes',
                'html_url' => 'https://github.com/tallyhome/OraNotes/releases/tag/v1.2.0',
                'published_at' => '2026-09-04T00:00:00Z',
                'assets' => [
                    [
                        'name' => 'oranotes-1.2.0.zip',
                        'browser_download_url' => 'https://github.com/tallyhome/OraNotes/releases/download/v1.2.0/oranotes-1.2.0.zip',
                    ],
                ],
            ], 200),
        ]);

        config(['oranotes.version' => '1.0.3']);
        $status = app(UpdateService::class)->status();

        $this->assertTrue($status['available']);
        $this->assertSame('1.2.0', $status['latest']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.github.com/'));
    }

    #[Test]
    public function test_malformed_repository_config_is_rejected(): void
    {
        config(['oranotes.update.repository' => 'https://evil.test/x']);

        $this->expectException(ValidationException::class);
        app(UpdateService::class)->repository();
    }
}
