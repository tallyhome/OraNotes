<?php

namespace App\Services\Install;

class EnvironmentDetector
{
    /**
     * @return array{os: string, sapi: string, server: string, panel: string|null, writable: bool, php: string}
     */
    public function detect(): array
    {
        $server = $_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI;
        $panel = null;
        if (is_dir('/usr/local/cpanel') || isset($_SERVER['CPANEL'])) {
            $panel = 'cPanel';
        } elseif (is_dir('/usr/local/psa') || is_dir('C:\\Program Files (x86)\\Plesk')) {
            $panel = 'Plesk';
        } elseif (is_dir('/usr/local/webuzo')) {
            $panel = 'Webuzo';
        }

        return [
            'os' => PHP_OS_FAMILY,
            'sapi' => PHP_SAPI,
            'server' => is_string($server) ? $server : PHP_SAPI,
            'panel' => $panel,
            'writable' => is_writable(base_path()) && is_writable(storage_path()),
            'php' => PHP_VERSION,
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    public function requirements(): array
    {
        $ext = [
            'mbstring', 'xml', 'curl', 'zip', 'gd', 'bcmath', 'intl', 'pdo',
        ];
        $checks = [
            ['name' => 'PHP ≥ 8.3', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'detail' => PHP_VERSION],
        ];
        foreach ($ext as $name) {
            $checks[] = ['name' => $name, 'ok' => extension_loaded($name), 'detail' => extension_loaded($name) ? 'ok' : 'manquante'];
        }
        $checks[] = [
            'name' => 'PDO SQLite ou MySQL',
            'ok' => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
            'detail' => implode(', ', array_filter([
                extension_loaded('pdo_sqlite') ? 'sqlite' : null,
                extension_loaded('pdo_mysql') ? 'mysql' : null,
            ])) ?: 'aucun',
        ];
        foreach (['storage', 'storage/app', 'storage/framework', 'bootstrap/cache'] as $rel) {
            $path = $rel === 'storage' ? storage_path() : base_path($rel);
            $ok = is_dir($path) && is_writable($path);
            $checks[] = ['name' => 'écriture '.$rel, 'ok' => $ok, 'detail' => $path];
        }

        return $checks;
    }
}
