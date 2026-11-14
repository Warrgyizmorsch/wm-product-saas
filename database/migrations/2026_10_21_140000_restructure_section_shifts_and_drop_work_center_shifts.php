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
        // 1. Rename production_work_center_section_shifts to production_section_shifts if needed
        if (Schema::hasTable('production_work_center_section_shifts') && !Schema::hasTable('production_section_shifts')) {
            Schema::rename('production_work_center_section_shifts', 'production_section_shifts');
        }

        // 2. Restructure production_section_shifts
        if (Schema::hasTable('production_section_shifts')) {
            Schema::table('production_section_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('production_section_shifts', 'workers_required') && !Schema::hasColumn('production_section_shifts', 'target_headcount')) {
                    $table->renameColumn('workers_required', 'target_headcount');
                }
            });

            Schema::table('production_section_shifts', function (Blueprint $table) {
                if (!Schema::hasColumn('production_section_shifts', 'active')) {
                    $table->boolean('active')->default(true)->after('target_headcount');
                }
            });
        }

        // 3. Drop obsolete production_work_center_shifts pivot table
        Schema::dropIfExists('production_work_center_shifts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Recreate production_work_center_shifts table
        if (!Schema::hasTable('production_work_center_shifts')) {
            Schema::create('production_work_center_shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'p_wcs_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('work_center_id');
                $table->foreign('work_center_id', 'pwcs_wc_id_fk')->references('id')->on('production_work_centers')->cascadeOnDelete();
                $table->unsignedBigInteger('shift_id');
                $table->foreign('shift_id', 'pwcs_shift_id_fk')->references('id')->on('production_shifts')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // 2. Revert production_section_shifts changes
        if (Schema::hasTable('production_section_shifts')) {
            Schema::table('production_section_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('production_section_shifts', 'active')) {
                    $table->dropColumn('active');
                }
                if (Schema::hasColumn('production_section_shifts', 'target_headcount') && !Schema::hasColumn('production_section_shifts', 'workers_required')) {
                    $table->renameColumn('target_headcount', 'workers_required');
                }
            });

            if (!Schema::hasTable('production_work_center_section_shifts')) {
                Schema::rename('production_section_shifts', 'production_work_center_section_shifts');
            }
        }
    }
};
