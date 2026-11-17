<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling on the Work Center → Section architecture: an operation runs on a Machine OR a Manual
 * Batch inside a Section. Order operations and schedule operations only carried work_center_id +
 * machine_id, so manual-batch operations and section shifts were invisible to the scheduler.
 */
return new class extends Migration
{
    private const TABLES = [
        'production_order_operations'    => ['prod_ord_op_sec_fk', 'prod_ord_op_sec_man_fk'],
        'production_schedule_operations' => ['prod_sch_op_sec_fk', 'prod_sch_op_sec_man_fk'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => [$sectionFk, $batchFk]) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $sectionFk, $batchFk) {
                if (!Schema::hasColumn($tableName, 'section_id')) {
                    $table->foreignId('section_id')
                        ->nullable()
                        ->after('work_center_id')
                        ->constrained('production_work_center_sections', indexName: $sectionFk)
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn($tableName, 'section_manual_production_id')) {
                    $table->foreignId('section_manual_production_id')
                        ->nullable()
                        ->after('machine_id')
                        ->constrained('production_section_manual_productions', indexName: $batchFk)
                        ->nullOnDelete();
                }
            });
        }

        // Backfill existing rows: order operations from their routing operation, schedule operations from their order operation.
        DB::statement(
            'UPDATE production_order_operations SET '
            . 'section_id = (SELECT ro.section_id FROM production_routing_operations ro WHERE ro.id = production_order_operations.routing_operation_id), '
            . 'section_manual_production_id = (SELECT ro.section_manual_production_id FROM production_routing_operations ro WHERE ro.id = production_order_operations.routing_operation_id) '
            . 'WHERE routing_operation_id IS NOT NULL AND section_id IS NULL AND section_manual_production_id IS NULL'
        );

        DB::statement(
            'UPDATE production_schedule_operations SET '
            . 'section_id = (SELECT oo.section_id FROM production_order_operations oo WHERE oo.id = production_schedule_operations.production_order_operation_id), '
            . 'section_manual_production_id = (SELECT oo.section_manual_production_id FROM production_order_operations oo WHERE oo.id = production_schedule_operations.production_order_operation_id) '
            . 'WHERE production_order_operation_id IS NOT NULL AND section_id IS NULL AND section_manual_production_id IS NULL'
        );
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName => [$sectionFk, $batchFk]) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $sectionFk, $batchFk) {
                if (Schema::hasColumn($tableName, 'section_manual_production_id')) {
                    $table->dropForeign($batchFk);
                    $table->dropColumn('section_manual_production_id');
                }

                if (Schema::hasColumn($tableName, 'section_id')) {
                    $table->dropForeign($sectionFk);
                    $table->dropColumn('section_id');
                }
            });
        }
    }
};
