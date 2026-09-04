<?php

namespace App\Services\Install;

use Illuminate\Support\Facades\Schema;

class InstallState
{
    public function lockPath(): string
    {
        return storage_path('app/'.config('oranotes.install.lock', 'installed.lock'));
    }

    public function isInstalled(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if (is_file($this->lockPath())) {
            return true;
        }

        if (! filled(config('app.key'))) {
            return false;
        }

        try {
            return Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }

    public function canRunWizard(): bool
    {
        return ! $this->isInstalled();
    }

    public function lock(string $version): void
    {
        $dir = dirname($this->lockPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => $version,
        ], JSON_PRETTY_PRINT));
    }
}
