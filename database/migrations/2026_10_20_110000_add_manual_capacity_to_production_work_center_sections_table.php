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
        Schema::table('production_work_center_sections', function (Blueprint $table) {
            $table->boolean('supports_manual_production')
                ->default(false)
                ->after('workers_required')
                ->comment('Whether this section supports manual (non-machine) production.');

            $table->decimal('manual_capacity_per_hour', 12, 4)
                ->nullable()
                ->after('supports_manual_production')
                ->comment('Manual production capacity per hour in units/hr when manual production is enabled.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_sections', function (Blueprint $table) {
            $table->dropColumn(['supports_manual_production', 'manual_capacity_per_hour']);
        });
    }
};
