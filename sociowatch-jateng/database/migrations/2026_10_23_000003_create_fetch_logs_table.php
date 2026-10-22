<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('new_posts')->default(0);
            $table->unsignedInteger('updated_posts')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('fetched_at')->useCurrent();
            $table->index(['social_account_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_logs');
    }
};
