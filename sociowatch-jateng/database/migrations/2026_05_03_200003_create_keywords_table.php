<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
            $table->enum('category', ['sensitif', 'negatif', 'netral', 'positif']);
            $table->string('color', 7)->default('#6b7280');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('post_keyword_pivot', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->integer('occurrence_count')->default(1);
            $table->primary(['post_id', 'keyword_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_keyword_pivot');
        Schema::dropIfExists('keywords');
    }
};
