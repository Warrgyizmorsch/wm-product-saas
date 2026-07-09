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
        // 1. Add missing indexes
        Schema::table('production_batch_genealogies', function (Blueprint $table) {
            $table->index('parent_batch_id', 'p_bg_parent_idx');
            $table->index('child_batch_id', 'p_bg_child_idx');
        });

        Schema::table('production_lot_traces', function (Blueprint $table) {
            $table->index(['source_type', 'source_id'], 'plt_source_idx');
            $table->index(['target_type', 'target_id'], 'plt_target_idx');
        });

        Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
            $table->index('quality_plan_id', 'pqpp_qp_idx');
        });

        Schema::table('production_quality_inspection_results', function (Blueprint $table) {
            $table->index('quality_inspection_id', 'pqir_qi_idx');
            $table->index('quality_plan_parameter_id', 'pqir_qpp_idx');
        });

        Schema::table('production_rework_operations', function (Blueprint $table) {
            $table->index('rework_order_id', 'prwo_rwo_idx');
        });

        Schema::table('production_scrap_disposals', function (Blueprint $table) {
            $table->index('ncr_id', 'psd_ncr_idx');
        });

        Schema::table('production_deviations', function (Blueprint $table) {
            $table->index('tenant_id', 'pdev_tenant_idx');
        });

        // 2. Replace global unique constraints with tenant-scoped composite unique constraints
        Schema::table('production_ncrs', function (Blueprint $table) {
            $table->dropUnique('production_ncrs_ncr_number_unique');
            $table->unique(['tenant_id', 'ncr_number'], 'pncr_tenant_number_unique');
        });

        Schema::table('production_capas', function (Blueprint $table) {
            $table->dropUnique('production_capas_capa_number_unique');
            $table->unique(['tenant_id', 'capa_number'], 'pcapa_tenant_number_unique');
        });

        Schema::table('production_rework_orders', function (Blueprint $table) {
            $table->dropUnique('production_rework_orders_rework_number_unique');
            $table->unique(['tenant_id', 'rework_number'], 'prw_tenant_number_unique');
        });

        Schema::table('production_deviations', function (Blueprint $table) {
            $table->dropUnique('production_deviations_deviation_number_unique');
            $table->unique(['tenant_id', 'deviation_number'], 'pdev_tenant_number_unique');
        });

        // 3. Add soft deletes to production_batches
        Schema::table('production_batches', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('production_deviations', function (Blueprint $table) {
            $table->dropUnique('pdev_tenant_number_unique');
            $table->unique('deviation_number', 'production_deviations_deviation_number_unique');
            $table->dropIndex('pdev_tenant_idx');
        });

        Schema::table('production_rework_orders', function (Blueprint $table) {
            $table->dropUnique('prw_tenant_number_unique');
            $table->unique('rework_number', 'production_rework_orders_rework_number_unique');
        });

        Schema::table('production_capas', function (Blueprint $table) {
            $table->dropUnique('pcapa_tenant_number_unique');
            $table->unique('capa_number', 'production_capas_capa_number_unique');
        });

        Schema::table('production_ncrs', function (Blueprint $table) {
            $table->dropUnique('pncr_tenant_number_unique');
            $table->unique('ncr_number', 'production_ncrs_ncr_number_unique');
        });

        Schema::table('production_scrap_disposals', function (Blueprint $table) {
            $table->dropIndex('psd_ncr_idx');
        });

        Schema::table('production_rework_operations', function (Blueprint $table) {
            $table->dropIndex('prwo_rwo_idx');
        });

        Schema::table('production_quality_inspection_results', function (Blueprint $table) {
            $table->dropIndex('pqir_qpp_idx');
            $table->dropIndex('pqir_qi_idx');
        });

        Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
            $table->dropIndex('pqpp_qp_idx');
        });

        Schema::table('production_lot_traces', function (Blueprint $table) {
            $table->dropIndex('plt_target_idx');
            $table->dropIndex('plt_source_idx');
        });

        Schema::table('production_batch_genealogies', function (Blueprint $table) {
            $table->dropIndex('p_bg_child_idx');
            $table->dropIndex('p_bg_parent_idx');
        });
    }
};
