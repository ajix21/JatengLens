<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', ['instagram', 'twitter', 'facebook', 'tiktok', 'youtube']);
            $table->string('post_url')->nullable();
            $table->text('content');
            $table->enum('media_type', ['text', 'image', 'video', 'reel', 'story'])->nullable();
            $table->bigInteger('likes_count')->default(0);
            $table->bigInteger('comments_count')->default(0);
            $table->bigInteger('shares_count')->default(0);
            $table->bigInteger('views_count')->default(0);
            $table->timestamp('posted_at');
            $table->boolean('is_flagged')->default(false);
            $table->text('flag_reason')->nullable();
            $table->timestamps();

            $table->index(['social_account_id', 'posted_at']);
            $table->index('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
