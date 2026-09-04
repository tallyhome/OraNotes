<?php

namespace Tests\Feature\Install;

use App\Services\Install\EnvironmentDetector;
use App\Services\Install\InstallState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallerLockTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_wizard_is_unavailable_when_the_app_is_already_installed(): void
    {
        $this->assertTrue(app(InstallState::class)->isInstalled());
        $this->assertFalse(app(InstallState::class)->canRunWizard());
        $this->get('/install')->assertNotFound();
    }

    #[Test]
    public function test_environment_detector_lists_required_extensions(): void
    {
        $checks = app(EnvironmentDetector::class)->requirements();
        $names = array_column($checks, 'name');

        $this->assertContains('PHP ≥ 8.3', $names);
        $this->assertContains('mbstring', $names);
        $this->assertContains('zip', $names);
    }
}
