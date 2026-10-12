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
                if (Schema::hasColumn('production_requisition_slips', 'production_order_id')) {
                    $table->unsignedBigInteger('production_order_id')->nullable()->change();
                }

                if (!Schema::hasColumn('production_requisition_slips', 'maintenance_work_order_id')) {
                    $table->unsignedBigInteger('maintenance_work_order_id')->nullable()->after('production_order_id');
                    $table->foreign('maintenance_work_order_id', 'fk_prs_mwo')
                        ->references('id')
                        ->on('production_maintenance_work_orders')
                        ->cascadeOnDelete();
                }

                if (!Schema::hasColumn('production_requisition_slips', 'source_type')) {
                    $table->string('source_type', 30)->default('production_order')->after('maintenance_work_order_id');
                }
            });
        }

        if (Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::table('production_maintenance_work_order_spares', function (Blueprint $table) {
                if (!Schema::hasColumn('production_maintenance_work_order_spares', 'production_requisition_slip_id')) {
                    $table->unsignedBigInteger('production_requisition_slip_id')->nullable()->after('maintenance_work_order_id');
                    $table->foreign('production_requisition_slip_id', 'fk_pmwos_prs')
                        ->references('id')
                        ->on('production_requisition_slips')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('production_maintenance_work_order_spares', 'production_requisition_slip_item_id')) {
                    $table->unsignedBigInteger('production_requisition_slip_item_id')->nullable()->after('production_requisition_slip_id');
                    $table->foreign('production_requisition_slip_item_id', 'fk_pmwos_prsi')
                        ->references('id')
                        ->on('production_requisition_slip_items')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::table('production_maintenance_work_order_spares', function (Blueprint $table) {
                if (Schema::hasColumn('production_maintenance_work_order_spares', 'production_requisition_slip_item_id')) {
                    $table->dropForeign('fk_pmwos_prsi');
                    $table->dropColumn('production_requisition_slip_item_id');
                }
                if (Schema::hasColumn('production_maintenance_work_order_spares', 'production_requisition_slip_id')) {
                    $table->dropForeign('fk_pmwos_prs');
                    $table->dropColumn('production_requisition_slip_id');
                }
            });
        }

        if (Schema::hasTable('production_requisition_slips')) {
            Schema::table('production_requisition_slips', function (Blueprint $table) {
                if (Schema::hasColumn('production_requisition_slips', 'source_type')) {
                    $table->dropColumn('source_type');
                }
                if (Schema::hasColumn('production_requisition_slips', 'maintenance_work_order_id')) {
                    $table->dropForeign('fk_prs_mwo');
                    $table->dropColumn('maintenance_work_order_id');
                }
                if (Schema::hasColumn('production_requisition_slips', 'production_order_id')) {
                    $table->unsignedBigInteger('production_order_id')->nullable(false)->change();
                }
            });
        }
    }
};
