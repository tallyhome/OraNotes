<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthService
{
    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->php(),
            $this->extensions(),
            $this->database(),
            $this->disk(),
            $this->writable(),
            $this->cache(),
            $this->queue(),
            $this->debug(),
            $this->https(),
            $this->versions(),
        ];
    }

    /**
     * @return array{ok: int, warning: int, error: int, checks: list<array{key: string, label: string, status: string, detail: string}>}
     */
    public function summary(): array
    {
        $checks = $this->checks();

        return [
            'ok' => count(array_filter($checks, fn ($c) => $c['status'] === 'ok')),
            'warning' => count(array_filter($checks, fn ($c) => $c['status'] === 'warning')),
            'error' => count(array_filter($checks, fn ($c) => $c['status'] === 'error')),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function php(): array
    {
        $min = (string) config('oranotes.update.min_php', '8.3.0');
        $ok = version_compare(PHP_VERSION, $min, '>=');

        return [
            'key' => 'php',
            'label' => 'PHP',
            'status' => $ok ? 'ok' : 'error',
            'detail' => PHP_VERSION.($ok ? '' : ' (minimum '.$min.')'),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function extensions(): array
    {
        $required = ['mbstring', 'xml', 'curl', 'zip', 'gd', 'bcmath', 'intl', 'pdo'];
        $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded($ext)));

        return [
            'key' => 'extensions',
            'label' => 'Extensions PHP',
            'status' => $missing === [] ? 'ok' : 'error',
            'detail' => $missing === [] ? 'Toutes présentes' : 'Manquantes : '.implode(', ', $missing),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function database(): array
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::getDriverName();

            return [
                'key' => 'database',
                'label' => 'Base de données',
                'status' => Schema::hasTable('users') ? 'ok' : 'warning',
                'detail' => $driver.(Schema::hasTable('users') ? '' : ' — tables non migrées'),
            ];
        } catch (\Throwable $e) {
            return [
                'key' => 'database',
                'label' => 'Base de données',
                'status' => 'error',
                'detail' => 'Connexion impossible',
            ];
        }
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function disk(): array
    {
        $free = @disk_free_space(base_path());
        $gb = $free !== false ? round($free / 1024 / 1024 / 1024, 2) : null;

        if ($gb === null) {
            return ['key' => 'disk', 'label' => 'Disque', 'status' => 'warning', 'detail' => 'Espace libre illisible'];
        }

        return [
            'key' => 'disk',
            'label' => 'Disque',
            'status' => $gb < 0.2 ? 'error' : ($gb < 1 ? 'warning' : 'ok'),
            'detail' => $gb.' Gio libres',
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function writable(): array
    {
        $paths = [
            storage_path(),
            storage_path('app'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            base_path('bootstrap/cache'),
        ];
        $bad = array_values(array_filter($paths, fn ($path) => ! is_writable($path)));

        return [
            'key' => 'writable',
            'label' => 'Permissions d’écriture',
            'status' => $bad === [] ? 'ok' : 'error',
            'detail' => $bad === [] ? 'storage/ et bootstrap/cache OK' : 'Non inscriptible : '.implode(', ', $bad),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function cache(): array
    {
        try {
            cache()->put('oranotes-health', 'ok', 10);

            return [
                'key' => 'cache',
                'label' => 'Cache',
                'status' => cache()->get('oranotes-health') === 'ok' ? 'ok' : 'warning',
                'detail' => (string) config('cache.default'),
            ];
        } catch (\Throwable) {
            return ['key' => 'cache', 'label' => 'Cache', 'status' => 'error', 'detail' => 'Échec lecture/écriture'];
        }
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function queue(): array
    {
        $driver = (string) config('queue.default');

        return [
            'key' => 'queue',
            'label' => 'Files',
            'status' => in_array($driver, ['sync', 'database'], true) ? 'ok' : 'warning',
            'detail' => $driver.' — le scheduler dépend de cron côté hôte',
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function debug(): array
    {
        $debug = (bool) config('app.debug');
        $prod = app()->isProduction();

        return [
            'key' => 'debug',
            'label' => 'APP_DEBUG',
            'status' => $prod && $debug ? 'error' : 'ok',
            'detail' => $debug ? 'activé' : 'désactivé',
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function https(): array
    {
        $url = (string) config('app.url');
        $secure = str_starts_with($url, 'https://');

        return [
            'key' => 'https',
            'label' => 'HTTPS',
            'status' => app()->isProduction() && ! $secure ? 'warning' : 'ok',
            'detail' => $url,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function versions(): array
    {
        return [
            'key' => 'versions',
            'label' => 'Versions',
            'status' => 'ok',
            'detail' => 'OraNotes '.config('oranotes.version').' · OraEditor '.config('oranotes.ora_editor_version').' · Laravel '.app()->version(),
        ];
    }
}
