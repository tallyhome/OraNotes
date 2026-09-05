<?php

namespace App\Services\Update;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Services\ActivityLogger;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateService
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array{current: string, latest: string|null, available: bool, changelog: string|null, html_url: string|null, published_at: string|null, asset: string|null, error: string|null, error_code: string|null, remediation: list<string>}
     */
    public function status(): array
    {
        $current = (string) config('oranotes.version');

        try {
            $release = $this->fetchLatestRelease();
        } catch (ConnectionException $e) {
            return $this->unavailableStatus($current, $e);
        } catch (ValidationException $e) {
            return $this->unavailableStatus($current, $e);
        }

        $latest = $release['tag'] ?? null;

        return [
            'current' => $current,
            'latest' => $latest,
            'available' => $latest !== null && version_compare($this->normalize($latest), $this->normalize($current), '>'),
            'changelog' => $release['body'] ?? null,
            'html_url' => $release['html_url'] ?? null,
            'published_at' => $release['published_at'] ?? null,
            'asset' => $release['asset_name'] ?? null,
            'error' => null,
            'error_code' => null,
            'remediation' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function latestRelease(): array
    {
        try {
            return $this->fetchLatestRelease();
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages([
                'update' => $this->transportErrorMessage($e),
            ]);
        }
    }

    /**
     * TLS verify option for GitHub HTTP calls. Always verifies — never `false`.
     */
    public function tlsVerifyOption(): bool|string
    {
        return $this->resolveCaBundlePath() ?? true;
    }

    public function resolveCaBundlePath(): ?string
    {
        $candidates = [
            config('oranotes.update.ca_bundle'),
            storage_path('app/cacert.pem'),
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            class_exists(CaBundle::class) ? CaBundle::getSystemCaRootBundlePath() : null,
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchLatestRelease(): array
    {
        $repo = $this->repository();
        $api = rtrim((string) config('oranotes.update.api'), '/');
        $url = $api.'/repos/'.$repo.'/releases/latest';

        $this->assertOfficialApi($url);

        $response = $this->updaterHttp((int) config('oranotes.update.timeout', 20))
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'update' => 'Impossible de consulter les versions officielles.',
            ]);
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['tag_name'])) {
            return [];
        }

        if (! empty($json['prerelease']) && ! config('oranotes.update.allow_prerelease')) {
            return [];
        }

        $asset = $this->officialAsset($json['assets'] ?? [], (string) $json['tag_name']);

        return [
            'tag' => ltrim((string) $json['tag_name'], 'v'),
            'body' => is_string($json['body'] ?? null) ? $json['body'] : null,
            'html_url' => is_string($json['html_url'] ?? null) ? $json['html_url'] : null,
            'published_at' => is_string($json['published_at'] ?? null) ? $json['published_at'] : null,
            'asset_url' => $asset['url'] ?? null,
            'asset_name' => $asset['name'] ?? null,
            'checksum_url' => $asset['checksum_url'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, errors: list<string>}
     */
    public function compatibility(string $targetVersion): array
    {
        $errors = [];
        $minPhp = (string) config('oranotes.update.min_php');
        if (version_compare(PHP_VERSION, $minPhp, '<')) {
            $errors[] = 'PHP '.$minPhp.' requis (actuel '.PHP_VERSION.').';
        }

        foreach (['mbstring', 'xml', 'curl', 'zip', 'pdo'] as $ext) {
            if (! extension_loaded($ext)) {
                $errors[] = 'Extension PHP manquante : '.$ext;
            }
        }

        $free = @disk_free_space(base_path());
        if ($free !== false && $free < 50 * 1024 * 1024) {
            $errors[] = 'Espace disque insuffisant (50 Mio minimum).';
        }

        foreach ([base_path(), storage_path(), base_path('bootstrap/cache')] as $path) {
            if (! is_writable($path)) {
                $errors[] = 'Répertoire non inscriptible : '.$path;
            }
        }

        $current = $this->normalize((string) config('oranotes.version'));
        $target = $this->normalize($targetVersion);
        if (version_compare($target, $current, '<')) {
            $errors[] = 'Le rétrogradage automatique est refusé ('.$current.' → '.$target.').';
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * Download, verify and apply the official release. Never accepts a user URL.
     *
     * @return array{ok: bool, version: string, backup: string|null, message: string}
     */
    public function apply(User $actor): array
    {
        $release = $this->latestRelease();
        $target = $release['tag'] ?? null;
        if (! is_string($target) || $target === '') {
            throw ValidationException::withMessages(['update' => 'Aucune version officielle disponible.']);
        }

        $compat = $this->compatibility($target);
        if (! $compat['ok']) {
            throw ValidationException::withMessages(['update' => $compat['errors']]);
        }

        if (empty($release['asset_url'])) {
            throw ValidationException::withMessages(['update' => 'L’archive officielle est absente de la release.']);
        }

        $this->assertOfficialDownload((string) $release['asset_url']);

        $work = storage_path('app/updates/'.Str::uuid());
        File::ensureDirectoryExists($work);

        try {
            $archive = $work.'/release.zip';
            $this->downloadOfficial((string) $release['asset_url'], $archive);
            $this->verifyIntegrity($archive, $release['checksum_url'] ?? null);
            $backup = $this->backup($target);
            Artisan::call('down', ['--retry' => 60, '--refresh' => 15]);

            try {
                $this->extractSafely($archive, $work.'/extract');
                $this->copySafe($work.'/extract', base_path());
                file_put_contents(base_path('VERSION'), $target.PHP_EOL);
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('up');
            } catch (\Throwable $e) {
                $this->restoreBackup($backup);
                Artisan::call('up');
                $this->logger->log(ActivityAction::AppRolledBack, $actor, null, [
                    'from' => $target,
                    'error' => 'échec application',
                ]);

                throw ValidationException::withMessages([
                    'update' => 'Mise à jour interrompue : restauration de la sauvegarde tentée. '.$e->getMessage(),
                ]);
            }

            $this->logger->log(ActivityAction::AppUpdated, $actor, null, [
                'version' => $target,
                'backup' => basename($backup),
            ]);

            return [
                'ok' => true,
                'version' => $target,
                'backup' => $backup,
                'message' => 'OraNotes a été mis à jour vers '.$target.'.',
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function repository(): string
    {
        $repo = (string) config('oranotes.update.repository');
        if (! preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repo)) {
            throw ValidationException::withMessages(['update' => 'Dépôt officiel mal configuré.']);
        }

        return $repo;
    }

    public function assertOfficialApi(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $allowed = parse_url((string) config('oranotes.update.api'), PHP_URL_HOST);

        if (! is_string($host) || $host !== $allowed || ! in_array($host, ['api.github.com'], true)) {
            throw ValidationException::withMessages(['update' => 'Source de mise à jour non autorisée.']);
        }
    }

    public function assertOfficialDownload(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $repo = $this->repository();

        $allowedHosts = ['github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];
        if (! in_array($host, $allowedHosts, true)) {
            throw ValidationException::withMessages(['update' => 'Téléchargement refusé : hôte non officiel.']);
        }

        if ($host === 'github.com' && ! str_contains($path, '/'.$repo.'/')) {
            throw ValidationException::withMessages(['update' => 'Téléchargement refusé : dépôt non officiel.']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $assets
     * @return array{url: string|null, name: string|null, checksum_url: string|null}
     */
    private function officialAsset(array $assets, string $tag): array
    {
        $zip = null;
        $sum = null;
        foreach ($assets as $asset) {
            $name = (string) ($asset['name'] ?? '');
            $url = (string) ($asset['browser_download_url'] ?? '');
            if ($url === '') {
                continue;
            }
            if (str_ends_with(strtolower($name), '.zip') && (str_contains(strtolower($name), 'oranotes') || $zip === null)) {
                $zip = ['url' => $url, 'name' => $name];
            }
            if (preg_match('/sha256|checksums/i', $name)) {
                $sum = $url;
            }
        }

        return [
            'url' => $zip['url'] ?? null,
            'name' => $zip['name'] ?? null,
            'checksum_url' => $sum,
        ];
    }

    private function downloadOfficial(string $url, string $dest): void
    {
        $this->assertOfficialDownload($url);

        try {
            $response = $this->updaterHttp(120)
                ->sink($dest)
                ->get($url);
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages(['update' => $this->transportErrorMessage($e)]);
        }

        if (! $response->successful() || ! is_file($dest) || filesize($dest) < 100) {
            throw ValidationException::withMessages(['update' => 'Téléchargement de l’archive officielle impossible.']);
        }
    }

    private function verifyIntegrity(string $archive, ?string $checksumUrl): void
    {
        if (! is_string($checksumUrl) || $checksumUrl === '') {
            return;
        }

        $this->assertOfficialDownload($checksumUrl);

        try {
            $body = $this->updaterHttp(20)->get($checksumUrl);
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages(['update' => $this->transportErrorMessage($e)]);
        }

        if (! $body->successful()) {
            throw ValidationException::withMessages(['update' => 'Somme de contrôle officielle illisible.']);
        }

        $hash = hash_file('sha256', $archive);
        $name = basename($archive);
        $matched = false;
        foreach (preg_split('/\R/', $body->body()) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{64})\s+\*?(\S+)/i', trim($line), $m)) {
                if (hash_equals(strtolower($m[1]), strtolower($hash)) && (basename($m[2]) === $name || str_contains($line, 'oranotes'))) {
                    $matched = true;
                    break;
                }
            }
        }

        if (! $matched && ! str_contains(strtolower($body->body()), strtolower($hash))) {
            throw ValidationException::withMessages(['update' => 'Intégrité de l’archive non vérifiée.']);
        }
    }

    private function backup(string $target): string
    {
        $dir = storage_path('app/backups/pre-'.$this->normalize((string) config('oranotes.version')).'-to-'.$this->normalize($target).'-'.date('YmdHis'));
        File::ensureDirectoryExists($dir);

        foreach (['.env', 'VERSION', 'composer.json', 'composer.lock'] as $file) {
            $from = base_path($file);
            if (is_file($from)) {
                copy($from, $dir.'/'.basename($file));
            }
        }

        $db = config('database.connections.'.config('database.default').'.database');
        if (is_string($db) && is_file($db)) {
            copy($db, $dir.'/database.sqlite');
        }

        file_put_contents($dir.'/manifest.json', json_encode([
            'from' => config('oranotes.version'),
            'to' => $target,
            'at' => now()->toIso8601String(),
            'atomic' => false,
        ], JSON_PRETTY_PRINT));

        return $dir;
    }

    private function restoreBackup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (['.env', 'VERSION', 'composer.json', 'composer.lock'] as $file) {
            if (is_file($dir.'/'.$file)) {
                copy($dir.'/'.$file, base_path($file));
            }
        }

        if (is_file($dir.'/database.sqlite')) {
            $db = config('database.connections.'.config('database.default').'.database');
            if (is_string($db) && $db !== '') {
                copy($dir.'/database.sqlite', $db);
            }
        }
    }

    private function extractSafely(string $archive, string $dest): void
    {
        File::ensureDirectoryExists($dest);
        $zip = new \ZipArchive;
        if ($zip->open($archive) !== true) {
            throw ValidationException::withMessages(['update' => 'Archive ZIP illisible.']);
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || $name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, "\0")) {
                    throw ValidationException::withMessages(['update' => 'Archive refusée (chemin dangereux).']);
                }
            }
            $zip->extractTo($dest);
        } finally {
            $zip->close();
        }
    }

    private function copySafe(string $extracted, string $target): void
    {
        $root = $this->unwrapSingleDirectory($extracted);
        $forbidden = ['.env', '.env.example', 'storage/logs', 'storage/app', 'node_modules', '.git'];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            if ($relative === '' || $this->isForbidden($relative, $forbidden)) {
                continue;
            }

            $dest = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $realTarget = realpath($target) ?: $target;
            $destDir = $file->isDir() ? $dest : dirname($dest);
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $resolved = realpath($destDir) ?: $destDir;
            if (! str_starts_with($resolved, $realTarget)) {
                throw ValidationException::withMessages(['update' => 'Copie hors du projet refusée.']);
            }
            if ($file->isFile()) {
                copy($file->getPathname(), $dest);
            }
        }
    }

    /**
     * @param  list<string>  $forbidden
     */
    private function isForbidden(string $relative, array $forbidden): bool
    {
        foreach ($forbidden as $item) {
            if ($relative === $item || str_starts_with($relative, $item.'/')) {
                return true;
            }
        }

        return false;
    }

    private function unwrapSingleDirectory(string $extracted): string
    {
        $items = array_values(array_filter(scandir($extracted) ?: [], fn ($item) => ! in_array($item, ['.', '..'], true)));
        if (count($items) === 1 && is_dir($extracted.'/'.$items[0])) {
            return $extracted.'/'.$items[0];
        }

        return $extracted;
    }

    private function updaterHttp(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)
            ->connectTimeout(min(10, $timeout))
            ->withOptions(['verify' => $this->tlsVerifyOption()])
            ->withHeaders(['User-Agent' => 'OraNotes-Updater']);
    }

    /**
     * @return array{current: string, latest: null, available: false, changelog: null, html_url: null, published_at: null, asset: null, error: string, error_code: string, remediation: list<string>}
     */
    private function unavailableStatus(string $current, \Throwable $e): array
    {
        $ssl = $this->isTlsCertificateError($e);

        return [
            'current' => $current,
            'latest' => null,
            'available' => false,
            'changelog' => null,
            'html_url' => null,
            'published_at' => null,
            'asset' => null,
            'error' => $this->transportErrorMessage($e),
            'error_code' => $ssl ? 'ssl_ca' : 'transport',
            'remediation' => $ssl ? $this->sslRemediationSteps() : [],
        ];
    }

    private function transportErrorMessage(\Throwable $e): string
    {
        if ($this->isTlsCertificateError($e)) {
            return 'Impossible de vérifier le certificat SSL de GitHub. PHP/cURL n’a pas de bundle d’autorités de certification (fréquent sous Windows, XAMPP, Laragon ou WAMP).';
        }

        if ($e instanceof ValidationException) {
            $messages = $e->errors()['update'] ?? [];

            return is_array($messages) ? (string) ($messages[0] ?? 'Vérification des mises à jour indisponible.') : (string) $messages;
        }

        return 'Vérification des mises à jour indisponible.';
    }

    private function isTlsCertificateError(\Throwable $e): bool
    {
        $haystack = strtolower($e->getMessage().' '.$e->getPrevious()?->getMessage());

        return str_contains($haystack, 'ssl certificate')
            || str_contains($haystack, 'unable to get local issuer')
            || str_contains($haystack, 'curl error 60')
            || str_contains($haystack, 'certificate verify failed')
            || str_contains($haystack, 'cainfo')
            || str_contains($haystack, 'ca bundle');
    }

    /**
     * @return list<string>
     */
    private function sslRemediationSteps(): array
    {
        return [
            'Dans php.ini, définissez curl.cainfo et openssl.cafile vers un fichier CA (cacert.pem Mozilla), puis redémarrez PHP/Apache.',
            'Ou définissez la variable d’environnement ORANOTES_CA_BUNDLE (ou CURL_CA_BUNDLE) avec le chemin absolu de ce fichier.',
            'Vous pouvez aussi placer le fichier dans storage/app/cacert.pem.',
        ];
    }

    private function normalize(string $version): string
    {
        return ltrim($version, 'vV');
    }
}
