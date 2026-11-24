<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narrative_briefings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('window_label', 100);
            $table->string('source', 20); // 'gemini' or 'heuristic'
            $table->string('status', 20); // 'ok' or 'fallback'
            $table->text('executive_summary');
            $table->json('issue_clusters');
            $table->json('recommendations');
            $table->json('stats_snapshot');
            $table->text('error_message')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narrative_briefings');
    }
};
