<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable();
            $table->boolean('auto_fetch')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_fetch_status', 20)->nullable();
            $table->text('last_fetch_error')->nullable();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable();
            $table->unique(['platform', 'external_id'], 'posts_platform_external_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique('posts_platform_external_id_unique');
            $table->dropColumn('external_id');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'auto_fetch', 'last_fetched_at', 'last_fetch_status', 'last_fetch_error']);
        });
    }
};
