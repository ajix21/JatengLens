<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_settings', function (Blueprint $table) {
            $table->integer('keyword_spike_threshold')->default(50)->after('milestone_values');
        });
    }

    public function down(): void
    {
        Schema::table('alert_settings', function (Blueprint $table) {
            $table->dropColumn('keyword_spike_threshold');
        });
    }
};
