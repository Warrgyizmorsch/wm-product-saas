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
        Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
            // Capacity sub-breakdowns (Machines & Manual Batches)
            if (!Schema::hasColumn('production_work_center_section_calculations', 'hourly_machine_capacity')) {
                $table->decimal('hourly_machine_capacity', 12, 4)->default(0.0000)->after('daily_capacity');
            }
            if (!Schema::hasColumn('production_work_center_section_calculations', 'daily_machine_capacity')) {
                $table->decimal('daily_machine_capacity', 14, 4)->default(0.0000)->after('hourly_machine_capacity');
            }
            if (!Schema::hasColumn('production_work_center_section_calculations', 'hourly_manual_capacity')) {
                $table->decimal('hourly_manual_capacity', 12, 4)->default(0.0000)->after('daily_machine_capacity');
            }
            if (!Schema::hasColumn('production_work_center_section_calculations', 'daily_manual_capacity')) {
                $table->decimal('daily_manual_capacity', 14, 4)->default(0.0000)->after('hourly_manual_capacity');
            }

            // Labor sub-breakdowns (Internal Salaried & External Contractors)
            if (!Schema::hasColumn('production_work_center_section_calculations', 'internal_labor_cost_per_hour')) {
                $table->decimal('internal_labor_cost_per_hour', 12, 4)->default(0.0000)->after('total_daily_labor_cost');
            }
            if (!Schema::hasColumn('production_work_center_section_calculations', 'total_daily_internal_labor_cost')) {
                $table->decimal('total_daily_internal_labor_cost', 14, 4)->default(0.0000)->after('internal_labor_cost_per_hour');
            }
            if (!Schema::hasColumn('production_work_center_section_calculations', 'external_labor_cost_per_hour')) {
                $table->decimal('external_labor_cost_per_hour', 12, 4)->default(0.0000)->after('total_daily_internal_labor_cost');
            }
            if (!Schema::hasColumn('production_work_center_section_calculations', 'total_daily_external_labor_cost')) {
                $table->decimal('total_daily_external_labor_cost', 14, 4)->default(0.0000)->after('external_labor_cost_per_hour');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
            $columns = [
                'hourly_machine_capacity',
                'daily_machine_capacity',
                'hourly_manual_capacity',
                'daily_manual_capacity',
                'internal_labor_cost_per_hour',
                'total_daily_internal_labor_cost',
                'external_labor_cost_per_hour',
                'total_daily_external_labor_cost',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('production_work_center_section_calculations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

