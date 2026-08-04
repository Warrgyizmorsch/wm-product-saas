<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE production_machine_downtimes MODIFY COLUMN start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            DB::statement("ALTER TABLE production_machine_state_histories MODIFY COLUMN started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
