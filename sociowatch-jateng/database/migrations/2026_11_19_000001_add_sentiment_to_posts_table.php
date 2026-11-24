<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('sentiment', 20)->default('netral')->after('flag_reason');
            $table->boolean('sentiment_manual')->default(false)->after('sentiment');
            $table->index(['sentiment', 'posted_at'], 'posts_sentiment_posted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_sentiment_posted_at_index');
            $table->dropColumn(['sentiment', 'sentiment_manual']);
        });
    }
};
