<?php

declare(strict_types=1);

$base = '/home/u504051894/domains/hamidbayat.co.uk';
$stage = $argv[1] ?? '';
if (! preg_match('~^'.preg_quote($base, '~').'/shared/update-[a-f0-9-]{36}$~D', $stage)) {
    throw new RuntimeException('Invalid private deployment stage');
}
$current = realpath($base.'/public_html/../..');
if (! $current || ! str_starts_with($current, $base.'/releases/')) {
    throw new RuntimeException('Invalid current release');
}
$config = require $current.'/backend/bootstrap/cache/config.php';
$manifest = json_decode(file_get_contents($stage.'/portfolio-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
$minimum = $manifest['minimum_php'] ?? '99.0.0';
if ($config['app']['env'] !== 'production' || $config['app']['debug']
    || $config['app']['url'] !== 'https://hamidbayat.co.uk'
    || version_compare(PHP_VERSION, $minimum, '<')) {
    throw new RuntimeException('Wrong production target or incompatible PHP');
}
foreach ($manifest['required_extensions'] as $extension) {
    if (! is_string($extension) || ! extension_loaded($extension)) {
        throw new RuntimeException('Required release extension unavailable');
    }
}
require $current.'/backend/vendor/autoload.php';
$app = require $current.'/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('platform.schema_version') !== $manifest['schema_from'] || $manifest['schema_from'] !== $manifest['schema_to']) {
    throw new RuntimeException('Schema change refused by one-click updater');
}
$server = Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() AS version')->version;
$engine = str_contains(strtolower($server), 'mariadb') ? 'mariadb' : 'mysql';
if ($engine !== $manifest['database_engine']
    || version_compare($server, $manifest['mysql_min'], '<')
    || version_compare($server, $manifest['mysql_max_exclusive'], '>=')) {
    throw new RuntimeException('Database runtime compatibility failed');
}
$db = $config['database']['connections'][$config['database']['default']];
$quote = static fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
file_put_contents($stage.'/backup-client.cnf', "[client]\nprotocol=tcp\nhost=".$quote($db['host'])."\nport=".(int) $db['port']."\nuser=".$quote($db['username'])."\npassword=".$quote($db['password'])."\n");
chmod($stage.'/backup-client.cnf', 0600);
file_put_contents($stage.'/database-name', $db['database']);
echo "Production target, schema and runtime verified; private backup options prepared.\n";
