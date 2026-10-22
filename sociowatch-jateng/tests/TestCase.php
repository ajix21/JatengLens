<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dijalankan RefreshDatabase SEBELUM migrate:fresh, sehingga database
     * produksi tidak sempat terhapus kalau konfigurasi salah.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->guardTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mencakup juga test yang tidak memakai RefreshDatabase.
        $this->guardTestDatabase();
    }

    /**
     * Gagal (exception) kalau database koneksi aktif tidak berakhiran _test.
     */
    protected function guardTestDatabase(?string $database = null): void
    {
        $database ??= (string) config('database.connections.' . config('database.default') . '.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Database test tidak aman: '{$database}' tidak berakhiran _test. Dihentikan agar data produksi tidak terhapus."
            );
        }
    }
}
