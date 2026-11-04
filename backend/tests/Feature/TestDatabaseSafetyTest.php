<?php

namespace Tests\Feature;

use App\Services\TestDatabaseSafety;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_owner_database_name_is_rejected_before_connection(): void
    {
        config(['database.connections.mysql.database' => 'hamid_portfolio']);
        DB::purge('mysql');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing database verification');
        app(TestDatabaseSafety::class)->verify();
    }

    public function test_database_url_override_is_rejected(): void
    {
        config(['database.connections.mysql.url' => 'mysql://example.invalid/another_test']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing database verification');
        app(TestDatabaseSafety::class)->verify();
    }
}
