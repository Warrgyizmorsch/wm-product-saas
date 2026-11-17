<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RT-IMPL-14 (RT-19): every routing operation figure is calculated once (form), checked by the backend and saved;
 * readers use the saved values. The operation also records the exact section snapshot (and Work Center snapshot)
 * the figures came from, for tracking and the "costs outdated" check.
 * RT-19 rule 6: an approval snapshot of the routing costs is stored on approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_routing_operations', 'labor_cost_per_unit')) {
                $table->decimal('labor_cost_per_unit', 15, 4)->nullable()->after('cost_per_unit');
            }
            if (!Schema::hasColumn('production_routing_operations', 'machine_cost_per_unit')) {
                $table->decimal('machine_cost_per_unit', 15, 4)->nullable()->after('labor_cost_per_unit');
            }
            if (!Schema::hasColumn('production_routing_operations', 'setup_cost_per_run')) {
                $table->decimal('setup_cost_per_run', 15, 4)->nullable()->after('machine_cost_per_unit');
            }
            if (!Schema::hasColumn('production_routing_operations', 'costs_calculated_at')) {
                $table->timestamp('costs_calculated_at')->nullable()->after('setup_cost_per_run');
            }
            if (!Schema::hasColumn('production_routing_operations', 'section_calculation_id')) {
                // no cascade: the saved figures stay even if a snapshot row is removed
                $table->foreignId('section_calculation_id')->nullable()->after('costs_calculated_at')
                    ->constrained('production_work_center_section_calculations')->nullOnDelete();
            }
            if (!Schema::hasColumn('production_routing_operations', 'section_snapshot_number')) {
                $table->unsignedInteger('section_snapshot_number')->nullable()->after('section_calculation_id');
            }
            if (!Schema::hasColumn('production_routing_operations', 'work_center_calculation_id')) {
                $table->foreignId('work_center_calculation_id')->nullable()->after('section_snapshot_number')
                    ->constrained('production_work_center_calculations')->nullOnDelete();
            }
        });

        if (!Schema::hasColumn('routings', 'cost_snapshot')) {
            Schema::table('routings', function (Blueprint $table) {
                $table->json('cost_snapshot')->nullable()->comment('RT-19 rule 6: costs recorded at approval');
            });
        }
    }

    public function down(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            foreach (['work_center_calculation_id', 'section_calculation_id'] as $fk) {
                if (Schema::hasColumn('production_routing_operations', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['section_snapshot_number', 'costs_calculated_at', 'setup_cost_per_run', 'machine_cost_per_unit', 'labor_cost_per_unit'] as $col) {
                if (Schema::hasColumn('production_routing_operations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('routings', 'cost_snapshot')) {
            Schema::table('routings', function (Blueprint $table) {
                $table->dropColumn('cost_snapshot');
            });
        }
    }
};
