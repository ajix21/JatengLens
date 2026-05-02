<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['instagram', 'twitter', 'facebook', 'tiktok', 'youtube']);
            $table->string('username');
            $table->string('display_name');
            $table->string('profile_url')->nullable();
            $table->string('profile_picture')->nullable();
            $table->bigInteger('followers_count')->default(0);
            $table->bigInteger('following_count')->default(0);
            $table->integer('post_count')->default(0);
            $table->text('bio')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->foreignId('region_id')->constrained()->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
