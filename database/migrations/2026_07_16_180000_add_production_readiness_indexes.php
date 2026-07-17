<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eager load and conflict check index for scheduling operations
        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'machine_id', 'planned_start', 'planned_finish'],
                'pso_tenant_machine_planned_range_idx'
            );
            $table->index(
                ['tenant_id', 'work_center_id', 'planned_start', 'planned_finish'],
                'pso_tenant_wc_planned_range_idx'
            );
        });

        // 2. Machine downtime lookup index
        Schema::table('production_machine_downtimes', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'machine_id', 'start_time', 'end_time'],
                'pmd_tenant_machine_time_range_idx'
            );
        });

        // 3. WIP status indexes
        Schema::table('production_wips', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'product_id', 'status'],
                'wips_tenant_prod_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->dropIndex('pso_tenant_machine_planned_range_idx');
            $table->dropIndex('pso_tenant_wc_planned_range_idx');
        });

        Schema::table('production_machine_downtimes', function (Blueprint $table) {
            $table->dropIndex('pmd_tenant_machine_time_range_idx');
        });

        Schema::table('production_wips', function (Blueprint $table) {
            $table->dropIndex('wips_tenant_prod_status_idx');
        });
    }
};
