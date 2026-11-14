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
        Schema::table('production_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('production_shifts', 'hourly_labor_rate')) {
                $table->decimal('hourly_labor_rate', 15, 4)
                    ->nullable()
                    ->after('overtime_allowed')
                    ->comment('Labor cost rate per worker per hour.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('production_shifts', 'hourly_labor_rate')) {
                $table->dropColumn('hourly_labor_rate');
            }
        });
    }
};
