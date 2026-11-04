<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestDatabaseSafety
{
    public function verify(): array
    {
        $connection = DB::connection();
        $configured = $connection->getDatabaseName();
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || $connection->getDriverName() !== 'mysql'
            || ! is_string($configured) || ! preg_match('/^[a-zA-Z0-9_]+_test$/D', $configured)
            || filled(config('database.connections.mysql.url')) || filled(getenv('DB_URL') ?: null)) {
            throw new RuntimeException('Refusing database verification: require testing environment, MySQL, an explicit *_test database and no DB_URL override.');
        }
        $actual = $connection->selectOne('SELECT DATABASE() AS name, VERSION() AS version');
        if ($actual->name !== $configured) {
            throw new RuntimeException('Refusing database operation: effective database differs from the configured *_test database.');
        }

        return [
            'connection' => config('database.default'),
            'host' => $connection->getConfig('host'),
            'configured_database' => $configured,
            'effective_database' => $actual->name,
            'server_version' => $actual->version,
            'db_url_override' => false,
        ];
    }
}
