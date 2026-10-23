<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_requisition_slips')) {
            Schema::table('production_requisition_slips', function (Blueprint $table) {
                if (!Schema::hasColumn('production_requisition_slips', 'rework_order_id')) {
                    $table->unsignedBigInteger('rework_order_id')->nullable()->after('maintenance_work_order_id');
                    $table->foreign('rework_order_id', 'fk_prs_rework_order')
                        ->references('id')
                        ->on('production_rework_orders')
                        ->cascadeOnDelete();
                }
            });
        }

        if (Schema::hasTable('production_requisition_slip_items')) {
            Schema::table('production_requisition_slip_items', function (Blueprint $table) {
                if (!Schema::hasColumn('production_requisition_slip_items', 'rework_operation_id')) {
                    $table->unsignedBigInteger('rework_operation_id')->nullable()->after('warehouse_id');
                    $table->foreign('rework_operation_id', 'fk_prsi_rework_op')
                        ->references('id')
                        ->on('production_rework_operations')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_requisition_slip_items')) {
            Schema::table('production_requisition_slip_items', function (Blueprint $table) {
                if (Schema::hasColumn('production_requisition_slip_items', 'rework_operation_id')) {
                    $table->dropForeign('fk_prsi_rework_op');
                    $table->dropColumn('rework_operation_id');
                }
            });
        }

        if (Schema::hasTable('production_requisition_slips')) {
            Schema::table('production_requisition_slips', function (Blueprint $table) {
                if (Schema::hasColumn('production_requisition_slips', 'rework_order_id')) {
                    $table->dropForeign('fk_prs_rework_order');
                    $table->dropColumn('rework_order_id');
                }
            });
        }
    }
};
