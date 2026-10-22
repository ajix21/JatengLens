<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    public function test_guard_throws_for_non_test_database_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->guardTestDatabase('nama_bukan_pengaman');
    }

    public function test_guard_accepts_test_database_name(): void
    {
        $this->guardTestDatabase('apapun_test');
        $this->assertTrue(true);
    }

    public function test_guard_default_checks_active_connection(): void
    {
        $this->guardTestDatabase();
        $this->assertStringEndsWith('_test', config('database.connections.' . config('database.default') . '.database'));
    }
}
