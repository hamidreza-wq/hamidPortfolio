<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication()
    {
        parent::refreshApplication();
        $connection = config('database.default');
        $database = DB::connection()->getDatabaseName();
        if ($connection !== 'mysql' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Tests require MySQL and a dedicated database with a name ending in _test.');
        }
    }
}
