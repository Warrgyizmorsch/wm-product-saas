<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 2.2: Remove obsolete machine-to-Work-Center direct relationship.
     * Machines belong to Sections only; Work Centers are derived through Sections.
     */
    public function up(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            if (Schema::hasColumn('production_machines', 'work_center_id')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->dropIndex('production_machines_tenant_id_work_center_id_index');
                    $table->dropConstrainedForeignId('work_center_id');
                } else {
                    $table->dropForeign('production_machines_work_center_id_foreign');
                    $table->dropIndex('production_machines_tenant_id_work_center_id_index');
                    $table->dropColumn('work_center_id');
                }
            }
        });

        if (Schema::hasTable('production_machine_downtimes') && Schema::hasColumn('production_machine_downtimes', 'work_center_id')) {
            Schema::table('production_machine_downtimes', function (Blueprint $table) {
                $table->unsignedBigInteger('work_center_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     * Restores structural column and constraints. Discarded values cannot be recovered.
     */
    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            if (!Schema::hasColumn('production_machines', 'work_center_id')) {
                $table->unsignedBigInteger('work_center_id')->nullable()->after('tenant_id');

                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('work_center_id', 'production_machines_work_center_id_foreign')
                        ->references('id')
                        ->on('production_work_centers')
                        ->cascadeOnDelete();
                    $table->index(['tenant_id', 'work_center_id'], 'production_machines_tenant_id_work_center_id_index');
                }
            }
        });
    }
};
