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
        Schema::table('production_work_centers', function (Blueprint $table) {
            if (!Schema::hasColumn('production_work_centers', 'total_theoretical_capacity')) {
                $table->decimal('total_theoretical_capacity', 14, 4)->nullable()->after('capacity_per_hour')
                    ->comment('Sum of theoretical capacities of all timeline windows.');
            }
            if (!Schema::hasColumn('production_work_centers', 'labor_cost_per_hour')) {
                $table->decimal('labor_cost_per_hour', 12, 4)->nullable()->after('cost_per_hour')
                    ->comment('Calculated average labor cost per unique operating hour across all section shifts.');
            }
            if (!Schema::hasColumn('production_work_centers', 'total_scheduled_labor_cost')) {
                $table->decimal('total_scheduled_labor_cost', 14, 4)->nullable()->after('labor_cost_per_hour')
                    ->comment('Sum of scheduled labor costs of all timeline windows.');
            }
            if (!Schema::hasColumn('production_work_centers', 'unique_operating_hours')) {
                $table->decimal('unique_operating_hours', 8, 4)->nullable()->after('total_scheduled_labor_cost')
                    ->comment('Duration in hours of the union of all assigned section shift intervals.');
            }
            if (!Schema::hasColumn('production_work_centers', 'calculation_details')) {
                $table->json('calculation_details')->nullable()->after('unique_operating_hours')
                    ->comment('JSON snapshot containing full timeline windows, active sections, active assignments, and costs.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            $columns = [
                'total_theoretical_capacity',
                'labor_cost_per_hour',
                'total_scheduled_labor_cost',
                'unique_operating_hours',
                'calculation_details',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('production_work_centers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
