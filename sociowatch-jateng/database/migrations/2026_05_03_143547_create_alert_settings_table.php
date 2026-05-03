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
        Schema::create('alert_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('spike_up_threshold', 5, 2)->default(10.00);
            $table->decimal('spike_down_threshold', 5, 2)->default(10.00);
            $table->json('milestone_values')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('social_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_settings');
    }
};
