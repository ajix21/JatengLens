<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.mysql.database');

        if (! str_ends_with($database, '_test')) {
            $this->fail("Database test tidak aman: '{$database}' tidak berakhiran _test. Hentikan agar data produksi tidak terhapus.");
        }
    }
}
