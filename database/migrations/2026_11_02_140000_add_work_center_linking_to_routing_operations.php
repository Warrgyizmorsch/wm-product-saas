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
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_routing_operations', 'section_id')) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('work_center_id')
                    ->constrained('production_work_center_sections', indexName: 'prod_rt_op_sec_fk')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('production_routing_operations', 'section_manual_production_id')) {
                $table->foreignId('section_manual_production_id')
                    ->nullable()
                    ->after('machine_id')
                    ->constrained('production_section_manual_productions', indexName: 'prod_rt_op_sec_man_fk')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('production_routing_operations', 'is_alternative')) {
                $table->boolean('is_alternative')
                    ->default(false)
                    ->after('section_manual_production_id');
            }

            if (!Schema::hasColumn('production_routing_operations', 'replaces_operation_id')) {
                $table->foreignId('replaces_operation_id')
                    ->nullable()
                    ->after('is_alternative')
                    ->constrained('production_routing_operations', indexName: 'prod_rt_op_rep_fk')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('production_routing_operations', 'priority')) {
                $table->integer('priority')
                    ->default(1)
                    ->after('replaces_operation_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operations', 'replaces_operation_id')) {
                $table->dropForeign('prod_rt_op_rep_fk');
                $table->dropColumn('replaces_operation_id');
            }

            if (Schema::hasColumn('production_routing_operations', 'is_alternative')) {
                $table->dropColumn('is_alternative');
            }

            if (Schema::hasColumn('production_routing_operations', 'priority')) {
                $table->dropColumn('priority');
            }

            if (Schema::hasColumn('production_routing_operations', 'section_manual_production_id')) {
                $table->dropForeign('prod_rt_op_sec_man_fk');
                $table->dropColumn('section_manual_production_id');
            }

            if (Schema::hasColumn('production_routing_operations', 'section_id')) {
                $table->dropForeign('prod_rt_op_sec_fk');
                $table->dropColumn('section_id');
            }
        });
    }
};
