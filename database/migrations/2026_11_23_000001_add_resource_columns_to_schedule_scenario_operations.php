<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling on the Work Center -> Section architecture: an operation runs on a Machine OR a Manual Batch of a section.
 * The what-if scenario sandbox copied only machine_id, so a manual-batch operation lost its batch inside a scenario
 * (no collision detection, no leveling, no lane on the dispatch board). Additive, nullable columns only — no data is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_schedule_scenario_operations', function (Blueprint $table) {
            if (! Schema::hasColumn('production_schedule_scenario_operations', 'section_id')) {
                $table->unsignedBigInteger('section_id')->nullable()->after('machine_id');
            }
            if (! Schema::hasColumn('production_schedule_scenario_operations', 'section_manual_production_id')) {
                $table->unsignedBigInteger('section_manual_production_id')->nullable()->after('section_id');
                $table->index('section_manual_production_id', 'psso_manual_batch_idx');
            }
            if (! Schema::hasColumn('production_schedule_scenario_operations', 'resource_id')) {
                $table->string('resource_id')->nullable()->after('section_manual_production_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_schedule_scenario_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_schedule_scenario_operations', 'section_manual_production_id')) {
                $table->dropIndex('psso_manual_batch_idx');
            }
            foreach (['section_id', 'section_manual_production_id', 'resource_id'] as $column) {
                if (Schema::hasColumn('production_schedule_scenario_operations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
