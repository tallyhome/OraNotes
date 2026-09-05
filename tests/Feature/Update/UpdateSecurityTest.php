<?php

namespace Tests\Feature\Update;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Update\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

    #[Test]
    public function test_ssl_certificate_failure_returns_friendly_status_without_raw_dump(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/tallyhome/OraNotes/releases/latest' => function () {
                throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate for https://api.github.com/repos/tallyhome/OraNotes/releases/latest');
            },
        ]);

        $status = app(UpdateService::class)->status();

        $this->assertFalse($status['available']);
        $this->assertNull($status['latest']);
        $this->assertSame('ssl_ca', $status['error_code']);
        $this->assertStringContainsString('certificat SSL', $status['error']);
        $this->assertStringContainsString('ORANOTES_CA_BUNDLE', $status['remediation'][1]);
        $this->assertStringNotContainsString('cURL error 60', $status['error']);
        $this->assertStringNotContainsString('unable to get local issuer', $status['error']);
        $this->assertStringNotContainsString('Stack', $status['error']);
        $this->assertStringNotContainsString('#0 ', $status['error']);
    }

    #[Test]
    public function test_admin_updates_page_exposes_friendly_ssl_status(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/tallyhome/OraNotes/releases/latest' => function () {
                throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate');
            },
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.updates'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Updates')
                ->where('status.available', false)
                ->where('status.latest', null)
                ->where('status.error_code', 'ssl_ca')
                ->has('status.remediation', 3)
                ->where('status.error', 'Impossible de vérifier le certificat SSL de GitHub. PHP/cURL n’a pas de bundle d’autorités de certification (fréquent sous Windows, XAMPP, Laragon ou WAMP).'));
    }

    #[Test]
    public function test_configured_ca_bundle_is_preferred_when_the_file_exists(): void
    {
        $path = storage_path('app/oranotes-test-ca.pem');
        file_put_contents($path, "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----\n");
        config(['oranotes.update.ca_bundle' => $path]);

        $this->assertSame($path, app(UpdateService::class)->tlsVerifyOption());

        unlink($path);
    }

    #[Test]
    public function test_tls_verify_is_never_disabled(): void
    {
        config(['oranotes.update.ca_bundle' => '/definitely/missing/cacert.pem']);

        $option = app(UpdateService::class)->tlsVerifyOption();

        $this->assertNotFalse($option);
        $this->assertTrue($option === true || (is_string($option) && is_file($option)));
    }
}
