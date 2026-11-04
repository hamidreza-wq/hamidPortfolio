<?php

namespace Tests;

use App\Services\TestDatabaseSafety;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication()
    {
        parent::refreshApplication();
        app(TestDatabaseSafety::class)->verify();
    }
}
