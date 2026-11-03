<?php

namespace App\Services\Platform;

use App\Models\ReleaseCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use ZipArchive;

class Releases
{
    public function installedVersion(): string
    {
        return trim(file_get_contents(base_path('../VERSION')));
    }

    public function check(): array
    {
        $release = app(GithubApi::class)->json('/repos/hamidreza-wq/hamidPortfolio/releases/latest', allowMissing: true);
        if ($release === null) {
            throw new IntegrationFailure('No published official GitHub release exists. Publish a signed release with portfolio-manifest.json, portfolio-manifest.sig and its ZIP asset before using updates.');
        }
        $tag = $release['tag_name'] ?? '';
        if (! is_string($tag) || ! preg_match('/^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/D', $tag)
            || ($release['draft'] ?? true) || ($release['prerelease'] ?? true)
            || ($release['html_url'] ?? '') !== 'https://github.com/hamidreza-wq/hamidPortfolio/releases/tag/'.$tag
            || ! is_array($release['assets'] ?? null)
            || (isset($release['body']) && ! is_string($release['body']))
            || ! is_string($release['published_at'] ?? null)
            || strtotime($release['published_at']) === false) {
            throw new IntegrationFailure('Release source, stable semantic version or release metadata is invalid.');
        }
        $assets = [];
        foreach ($release['assets'] as $asset) {
            if (! is_array($asset) || ! is_string($asset['name'] ?? null)
                || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,100}$/D', $asset['name'])
                || ! is_int($asset['id'] ?? null) || ! is_int($asset['size'] ?? null)
                || $asset['size'] < 1 || isset($assets[$asset['name']])) {
                throw new IntegrationFailure('Release asset metadata is invalid or duplicated.');
            }
            $assets[$asset['name']] = [
                'id' => $asset['id'], 'name' => $asset['name'],
                'size' => $asset['size'], 'digest' => $asset['digest'] ?? null,
            ];
        }
        if (version_compare(substr($tag, 1), $this->installedVersion(), '<=')) {
            return ['message' => 'No newer stable application release is available.', 'tag' => $tag];
        }
        $candidate = ReleaseCandidate::firstOrNew(['tag' => $tag]);
        if ($candidate->exists && $candidate->release_assets !== $assets) {
            $candidate->fill([
                'status' => 'available', 'verified_at' => null, 'approved_at' => null,
                'approved_by' => null, 'manifest' => null, 'inspection' => null,
                'artifact_path' => null, 'artifact_sha256' => null,
            ]);
        }
        $candidate->fill([
            'version' => substr($tag, 1), 'notes' => Str::limit((string) ($release['body'] ?? ''), 20000, ''),
            'released_at' => $release['published_at'], 'release_assets' => $assets,
        ])->save();
        app(Notices::class)->put('release:'.$tag, 'updates', 'medium', 'Application release available',
            $tag.' is available. Signature, artifact and runtime verification are required; nothing has been deployed.', 'admin.platform.updates');

        return ['message' => 'New release discovered; it is not yet verified.', 'tag' => $tag];
    }

    public function prepare(ReleaseCandidate $candidate): void
    {
        $candidate->update([
            'status' => 'preparing', 'verified_at' => null, 'approved_at' => null,
            'approved_by' => null, 'artifact_path' => null, 'artifact_sha256' => null,
        ]);
        try {
            $this->verify($candidate);
        } catch (IntegrationFailure $exception) {
            $candidate->update(['status' => 'failed']);
            throw $exception;
        }
    }

    private function verify(ReleaseCandidate $candidate): void
    {
        if (version_compare($candidate->version, $this->installedVersion(), '<=')) {
            throw new IntegrationFailure('Update verification refuses the installed version or an older release.');
        }
        if (! function_exists('sodium_crypto_sign_verify_detached') || ! class_exists(ZipArchive::class)) {
            throw new IntegrationFailure('Release verification requires sodium and zip PHP extensions.');
        }
        $key = base64_decode((string) config('platform.release_public_key'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new IntegrationFailure('A trusted Ed25519 RELEASE_PUBLIC_KEY must be configured on the server.');
        }
        $api = app(GithubApi::class);
        $assets = $candidate->release_assets;
        if (! isset($assets['portfolio-manifest.json'], $assets['portfolio-manifest.sig'])) {
            throw new IntegrationFailure('The release is missing its signed manifest assets.');
        }
        $bytes = $api->asset($assets['portfolio-manifest.json'], 65536);
        $signature = base64_decode(trim($api->asset($assets['portfolio-manifest.sig'], 1024)), true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $bytes, $key)) {
            throw new IntegrationFailure('Release manifest signature is missing, invalid or signed with an untrusted key.');
        }
        try {
            $manifest = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new IntegrationFailure('Signed release manifest is not valid JSON.');
        }
        $this->validateManifest($manifest, $candidate);
        $name = $manifest['asset']['name'];
        if (! isset($assets[$name]) || ! str_ends_with($name, '.zip')) {
            throw new IntegrationFailure('Signed artifact is not a ZIP asset on this release.');
        }
        $digest = 'sha256:'.$manifest['asset']['sha256'];
        if (($assets[$name]['digest'] ?? null) !== $digest) {
            throw new IntegrationFailure('GitHub asset SHA-256 digest is missing or does not match the signed manifest.');
        }
        $root = Storage::disk('local')->path('platform/releases');
        Storage::disk('local')->makeDirectory('platform/releases');
        $free = disk_free_space($root);
        if ($free === false || $free < 256 * 1024 * 1024 || ! is_writable($root)) {
            throw new IntegrationFailure('Private artifact storage needs write permission and at least 256 MiB of free space.');
        }
        $artifact = $api->asset($assets[$name], 50 * 1024 * 1024);
        if (! hash_equals($manifest['asset']['sha256'], hash('sha256', $artifact))) {
            throw new IntegrationFailure('Downloaded artifact failed SHA-256 verification.');
        }
        $path = 'platform/releases/'.Str::uuid().'.zip';
        if (! Storage::disk('local')->put($path, $artifact)
            || ! hash_equals($manifest['asset']['sha256'], hash_file('sha256', Storage::disk('local')->path($path)))) {
            throw new IntegrationFailure('Artifact could not be written and verified in private storage.');
        }
        $inspection = $this->inspectArchive(Storage::disk('local')->path($path));
        $candidate->update([
            'status' => 'awaiting_approval', 'manifest' => $manifest, 'inspection' => [...$inspection, 'trust_key_sha256' => hash('sha256', $key)],
            'artifact_path' => $path, 'artifact_sha256' => $manifest['asset']['sha256'], 'verified_at' => now(),
        ]);
    }

    private function validateManifest(mixed $manifest, ReleaseCandidate $candidate): void
    {
        if (! is_array($manifest)
            || ($manifest['repository'] ?? '') !== 'hamidreza-wq/hamidPortfolio'
            || ($manifest['tag'] ?? '') !== $candidate->tag
            || ($manifest['version'] ?? '') !== $candidate->version
            || ! is_string($manifest['notes'] ?? null) || strlen($manifest['notes']) > 20000
            || ! is_string($manifest['released_at'] ?? null) || strtotime($manifest['released_at']) === false
            || ! is_string($manifest['asset']['name'] ?? null)
            || ! is_string($manifest['asset']['sha256'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/D', $manifest['asset']['sha256'] ?? '')
            || ! is_string($manifest['minimum_php'] ?? null)
            || ! preg_match('/^\d+\.\d+\.\d+$/D', $manifest['minimum_php'] ?? '')
            || ! is_array($manifest['required_extensions'] ?? null)
            || count($manifest['required_extensions']) > 40
            || ! is_string($manifest['mysql_min'] ?? null)
            || ! preg_match('/^\d+\.\d+\.\d+$/D', $manifest['mysql_min'] ?? '')
            || ! is_string($manifest['mysql_max_exclusive'] ?? null)
            || ! preg_match('/^\d+\.\d+\.\d+$/D', $manifest['mysql_max_exclusive'] ?? '')
            || ! in_array($manifest['database_engine'] ?? '', ['mysql', 'mariadb'], true)
            || ($manifest['schema_from'] ?? null) !== config('platform.schema_version')
            || ! is_int($manifest['schema_to'] ?? null)
            || $manifest['schema_to'] < $manifest['schema_from']) {
            throw new IntegrationFailure('Signed manifest source, version, runtime or schema compatibility fields are invalid.');
        }
        if (version_compare(PHP_VERSION, $manifest['minimum_php'], '<')) {
            throw new IntegrationFailure('This release requires a newer PHP version.');
        }
        foreach ($manifest['required_extensions'] as $extension) {
            if (! is_string($extension) || ! preg_match('/^[a-zA-Z0-9_]{1,40}$/D', $extension) || ! extension_loaded($extension)) {
                throw new IntegrationFailure('A required PHP extension is invalid or missing.');
            }
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new IntegrationFailure('Release preparation requires MySQL.');
        }
        $mysql = DB::selectOne('SELECT VERSION() AS version')->version;
        $engine = stripos($mysql, 'mariadb') !== false ? 'mariadb' : 'mysql';
        if ($engine !== $manifest['database_engine']
            || version_compare($mysql, $manifest['mysql_min'], '<')
            || version_compare($mysql, $manifest['mysql_max_exclusive'], '>=')) {
            throw new IntegrationFailure('MySQL runtime is outside the release compatibility range.');
        }
    }

    public function inspectArchive(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new IntegrationFailure('Release archive is malformed.');
        }
        $total = 0;
        $migrations = [];
        $seen = [];
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > 10000) {
                throw new IntegrationFailure('Release archive entry count is unsafe.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $parts = explode('/', rtrim($name, '/'));
                $os = $attributes = 0;
                if ($name === '' || strlen($name) > 240 || preg_match('/[\\\\:\x00-\x1f\x7f]/', $name)
                    || str_starts_with($name, '/') || array_intersect(['..', '.', ''], $parts)
                    || preg_match('/[. ](?:\/|$)/', $name)
                    || isset($seen[strtolower($name)])
                    || ! $zip->getExternalAttributesIndex($i, $os, $attributes)
                    || ! in_array(($attributes >> 16) & 0170000, [0, 0100000, 0040000], true)
                    || preg_match('~(?:^|/)(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:[./]|$)~i', $name)
                    || ! empty($entry['encryption_method'])) {
                    throw new IntegrationFailure('Release archive contains unsafe paths, duplicates, encryption or symlinks.');
                }
                $seen[strtolower($name)] = true;
                $total += $entry['size'];
                if ($entry['size'] > 50 * 1024 * 1024 || $total > 200 * 1024 * 1024) {
                    throw new IntegrationFailure('Release archive exceeds expansion limits.');
                }
                if (! str_ends_with($name, '/')) {
                    $stream = $zip->getStream($name);
                    if ($stream === false) {
                        throw new IntegrationFailure('Release archive entry cannot be decompressed.');
                    }
                    $crc = hash_init('crc32b');
                    $bytes = 0;
                    try {
                        while (! feof($stream)) {
                            $chunk = fread($stream, 65536);
                            if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                                throw new IntegrationFailure('Release archive entry is corrupt.');
                            }
                            $bytes += strlen($chunk);
                            if ($bytes > $entry['size']) {
                                throw new IntegrationFailure('Archive entry exceeds its declared expansion limit.');
                            }
                            hash_update($crc, $chunk);
                        }
                    } finally {
                        fclose($stream);
                    }
                    if ($bytes !== $entry['size'] || hash_final($crc) !== sprintf('%08x', $entry['crc'])) {
                        throw new IntegrationFailure('Release archive CRC or expanded size is invalid.');
                    }
                }
                if (preg_match('~(?:^|/)database/migrations/[^/]+\.php$~', $name)) {
                    if ($entry['size'] > 262144) {
                        throw new IntegrationFailure('Migration file exceeds review limits.');
                    }
                    $source = $zip->getFromIndex($i);
                    if ($source === false) {
                        throw new IntegrationFailure('A migration could not be inspected.');
                    }
                    $migrations[] = ['path' => $name, 'potentially_destructive' => (bool) preg_match('/\b(drop|dropIfExists|dropColumn|truncate|delete|statement|unprepared|change|renameColumn)\s*\(/i', $source)];
                }
            }
        } finally {
            $zip->close();
        }

        return [
            'entries' => count($seen), 'expanded_bytes' => $total, 'migrations' => $migrations,
            'deployment' => 'disabled', 'backups' => 'not configured or verified',
            'warning' => 'Archive verified and inspected only; not extracted or executed. Migration flags are heuristic: human review of every migration is mandatory.',
        ];
    }
}
