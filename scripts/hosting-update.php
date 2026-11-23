<?php

declare(strict_types=1);

// Secrets loaded during the checks; any failure message is redacted against them before printing.
$secrets = [];

try {
    $base = '/home/u504051894/domains/hamidbayat.co.uk';
    $stage = $argv[1] ?? '';
    if (! preg_match('~^'.preg_quote($base, '~').'/shared/update-[a-f0-9-]{36}$~D', $stage)) {
        throw new RuntimeException('Invalid private deployment stage');
    }
    $current = realpath($base.'/public_html/../..');
    if (! $current || ! str_starts_with($current, $base.'/releases/')) {
        throw new RuntimeException('Invalid current release');
    }
    $manifest = json_decode(file_get_contents($stage.'/portfolio-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $minimum = $manifest['minimum_php'] ?? '99.0.0';
    if (version_compare(PHP_VERSION, $minimum, '<')) {
        throw new RuntimeException('Incompatible PHP: command-line PHP '.PHP_VERSION.' is older than '.$minimum);
    }
    $missing = array_values(array_filter(
        $manifest['required_extensions'],
        static fn ($extension): bool => ! is_string($extension) || ! extension_loaded($extension),
    ));
    if ($missing !== []) {
        throw new RuntimeException('Required PHP extensions unavailable to command-line PHP '.PHP_VERSION.': '
            .implode(', ', array_map(static fn ($e): string => is_string($e) ? $e : gettype($e), $missing)));
    }
    // Load the installed release's configuration through Laravel, which uses
    // bootstrap/cache/config.php when present and the private .env otherwise.
    require $current.'/backend/vendor/autoload.php';
    $app = require $current.'/backend/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $config = ['app' => config('app'), 'database' => config('database')];
    $db = $config['database']['connections'][$config['database']['default']] ?? [];
    $secrets = array_filter([$db['password'] ?? null, $config['app']['key'] ?? null], 'is_string');
    if ($config['app']['env'] !== 'production') {
        throw new RuntimeException('Wrong production target: APP_ENV is not production');
    }
    if ($config['app']['debug']) {
        throw new RuntimeException('Wrong production target: APP_DEBUG is enabled');
    }
    if ($config['app']['url'] !== 'https://hamidbayat.co.uk') {
        throw new RuntimeException('Wrong production target: APP_URL is not https://hamidbayat.co.uk');
    }
    $installedSchema = config('platform.schema_version');
    if ($installedSchema !== $manifest['schema_from'] || $manifest['schema_from'] !== $manifest['schema_to']) {
        throw new RuntimeException('Schema change refused by one-click updater (installed '
            .var_export($installedSchema, true).', release '.var_export($manifest['schema_from'], true)
            .' -> '.var_export($manifest['schema_to'], true).')');
    }
    $server = Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() AS version')->version;
    $engine = str_contains(strtolower($server), 'mariadb') ? 'mariadb' : 'mysql';
    if ($engine !== $manifest['database_engine']
        || version_compare($server, $manifest['mysql_min'], '<')
        || version_compare($server, $manifest['mysql_max_exclusive'], '>=')) {
        throw new RuntimeException('Database runtime compatibility failed: server '.$server.', release requires '
            .$manifest['database_engine'].' >= '.$manifest['mysql_min'].' and < '.$manifest['mysql_max_exclusive']);
    }
    $quote = static fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
    file_put_contents($stage.'/backup-client.cnf', "[client]\nprotocol=tcp\nhost=".$quote($db['host'])."\nport=".(int) $db['port']."\nuser=".$quote($db['username'])."\npassword=".$quote($db['password'])."\n");
    chmod($stage.'/backup-client.cnf', 0600);
    file_put_contents($stage.'/database-name', $db['database']);
    echo "Production target, schema and runtime verified; private backup options prepared.\n";
} catch (Throwable $exception) {
    // Print why the pre-check refused, so a failed run is diagnosable from the Actions log.
    $message = $exception::class.': '.$exception->getMessage();
    foreach ($secrets as $secret) {
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }
    }
    fwrite(STDERR, 'Deployment pre-check failed: '.substr($message, 0, 500)."\n");
    exit(1);
}
