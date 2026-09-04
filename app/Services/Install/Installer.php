<?php

namespace App\Services\Install;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class Installer
{
    public function __construct(
        private InstallState $state,
        private EnvironmentDetector $detector,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function install(array $config): User
    {
        if (! $this->state->canRunWizard()) {
            throw ValidationException::withMessages([
                'install' => 'OraNotes est déjà installé. Le wizard est verrouillé.',
            ]);
        }

        foreach ($this->detector->requirements() as $check) {
            if (! $check['ok'] && str_starts_with($check['name'], 'PHP')) {
                throw ValidationException::withMessages(['install' => $check['name'].' : '.$check['detail']]);
            }
        }

        $this->writeEnv($config);
        Artisan::call('config:clear');

        if (! filled(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        $this->testDatabase($config['database'] ?? []);

        if (($config['database']['driver'] ?? 'sqlite') === 'sqlite') {
            $path = $config['database']['database'] ?? database_path('database.sqlite');
            if (! is_file($path)) {
                File::put($path, '');
            }
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('storage:link');

        $admin = User::query()->create([
            'name' => $config['admin']['name'],
            'email' => $config['admin']['email'],
            'password' => $config['admin']['password'],
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        app(WorkspaceService::class)->createDefaultFor($admin);

        $this->patchEnv(['APP_DEBUG' => ($config['app']['env'] ?? 'production') === 'local' ? 'true' : 'false']);
        $this->state->lock((string) config('oranotes.version'));
        Artisan::call('config:clear');

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $database
     */
    public function testDatabase(array $database): void
    {
        $driver = $database['driver'] ?? 'sqlite';
        config([
            'database.default' => $driver,
            'database.connections.'.$driver => array_filter([
                'driver' => $driver,
                'host' => $database['host'] ?? '127.0.0.1',
                'port' => $database['port'] ?? 3306,
                'database' => $database['database'] ?? database_path('database.sqlite'),
                'username' => $database['username'] ?? null,
                'password' => $database['password'] ?? null,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ], fn ($v) => $v !== null),
        ]);

        try {
            DB::purge($driver);
            DB::connection($driver)->getPdo();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'database' => 'Connexion impossible. Vérifiez hôte, port, base, utilisateur et mot de passe.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeEnv(array $config): void
    {
        $example = base_path('.env.example');
        $env = base_path('.env');
        if (! is_file($env) && is_file($example)) {
            copy($example, $env);
        }

        $db = $config['database'] ?? [];
        $app = $config['app'] ?? [];
        $this->patchEnv([
            'APP_NAME' => $app['name'] ?? 'OraNotes',
            'APP_ENV' => $app['env'] ?? 'production',
            'APP_URL' => $app['url'] ?? 'http://localhost',
            'APP_LOCALE' => $app['locale'] ?? 'fr',
            'DB_CONNECTION' => $db['driver'] ?? 'sqlite',
            'DB_HOST' => $db['host'] ?? '127.0.0.1',
            'DB_PORT' => (string) ($db['port'] ?? '3306'),
            'DB_DATABASE' => (string) ($db['database'] ?? 'database/database.sqlite'),
            'DB_USERNAME' => (string) ($db['username'] ?? ''),
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function patchEnv(array $values): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return;
        }

        $contents = file_get_contents($path) ?: '';
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->envValue($value);
            if (preg_match('/^'.preg_quote($key, '/').'=/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents, 1) ?? $contents;
            } else {
                $contents .= PHP_EOL.$line;
            }
        }
        file_put_contents($path, $contents);
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#\'"]/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
