<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE alerts MODIFY COLUMN alert_type ENUM('spike_up','spike_down','milestone','keyword_spike') NOT NULL");
        }
        // SQLite: enum is stored as text, no ALTER needed
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE alerts MODIFY COLUMN alert_type ENUM('spike_up','spike_down','milestone') NOT NULL");
        }
    }
};
