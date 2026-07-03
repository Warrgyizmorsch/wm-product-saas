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
        if (Schema::hasTable('attendance_penalties')) {
            Schema::table('attendance_penalties', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_penalties', 'penalty_tiers')) {
                    $table->json('penalty_tiers')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance_penalties')) {
            Schema::table('attendance_penalties', function (Blueprint $table) {
                if (Schema::hasColumn('attendance_penalties', 'penalty_tiers')) {
                    $table->dropColumn('penalty_tiers');
                }
            });
        }
    }
};
