<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add FULLTEXT for MySQL; SQLite doesn't support it
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE posts ADD FULLTEXT INDEX posts_content_fulltext (content)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE posts DROP INDEX posts_content_fulltext');
        }
    }
};
