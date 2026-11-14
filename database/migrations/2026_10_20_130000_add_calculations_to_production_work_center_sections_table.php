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
            $table->decimal('capacity_per_hour', 12, 4)
                ->default(0)
                ->after('manual_capacity_per_hour')
                ->comment('Calculated theoretical production capacity per hour (machines + manual).');

            $table->decimal('labor_cost_per_hour', 12, 4)
                ->default(0)
                ->after('capacity_per_hour')
                ->comment('Calculated average scheduled labor cost per operating hour.');

            $table->decimal('total_scheduled_labor_cost', 14, 4)
                ->default(0)
                ->after('labor_cost_per_hour')
                ->comment('Total scheduled labor cost across distinct operating timeline windows.');

            $table->decimal('unique_operating_hours', 8, 4)
                ->default(0)
                ->after('total_scheduled_labor_cost')
                ->comment('Unique elapsed scheduled operating hours (overlapping hours counted once, gaps excluded).');

            $table->json('calculation_details')
                ->nullable()
                ->after('unique_operating_hours')
                ->comment('JSON snapshot of capacity breakdown and timeline-window labor cost breakdown.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_sections', function (Blueprint $table) {
            $table->dropColumn([
                'capacity_per_hour',
                'labor_cost_per_hour',
                'total_scheduled_labor_cost',
                'unique_operating_hours',
                'calculation_details',
            ]);
        });
    }
};
