<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#6366f1');
            $table->timestamps();
        });

        Schema::create('post_tag_pivot', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'post_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag_pivot');
        Schema::dropIfExists('post_tags');
    }
};
