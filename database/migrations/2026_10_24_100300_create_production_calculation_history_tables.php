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
        // 1. Create production_work_center_calculations (parent roll-up calculation history)
        if (!Schema::hasTable('production_work_center_calculations')) {
            Schema::create('production_work_center_calculations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pwcc_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('work_center_id');
                $table->foreign('work_center_id', 'pwcc_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();

                $table->uuid('batch_id');
                $table->string('trigger_type', 50)->default('manual');
                $table->json('impacted_section_ids')->nullable();

                $table->decimal('capacity_per_hour', 12, 4)->default(0.0000);
                $table->decimal('daily_capacity', 14, 4)->default(0.0000);
                $table->decimal('labor_cost_per_hour', 12, 4)->default(0.0000);
                $table->decimal('total_daily_labor_cost', 14, 4)->default(0.0000);
                $table->decimal('machine_cost_per_hour', 12, 4)->default(0.0000);
                $table->decimal('total_daily_machine_cost', 14, 4)->default(0.0000);
                $table->decimal('overhead_cost', 14, 4)->default(0.0000);
                $table->decimal('total_daily_cost', 14, 4)->default(0.0000);
                $table->decimal('total_cost_per_hour', 12, 4)->default(0.0000);
                $table->decimal('unique_operating_hours', 8, 4)->default(0.0000);

                $table->longText('calculation_details')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'work_center_id'], 'pwcc_tenant_wc_idx');
                $table->index(['batch_id'], 'pwcc_batch_idx');
            });
        }

        // 2. Create production_work_center_section_calculations (section-level calculation history)
        if (!Schema::hasTable('production_work_center_section_calculations')) {
            Schema::create('production_work_center_section_calculations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pwcsc_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('work_center_id');
                $table->foreign('work_center_id', 'pwcsc_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('section_id');
                $table->foreign('section_id', 'pwcsc_sec_fk')
                    ->references('id')
                    ->on('production_work_center_sections')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('work_center_calculation_id')->nullable();
                $table->foreign('work_center_calculation_id', 'pwcsc_wcc_fk')
                    ->references('id')
                    ->on('production_work_center_calculations')
                    ->nullOnDelete();

                $table->uuid('batch_id');
                $table->string('trigger_type', 50)->default('manual');

                $table->decimal('capacity_per_hour', 12, 4)->default(0.0000);
                $table->decimal('daily_capacity', 14, 4)->default(0.0000);
                $table->decimal('labor_cost_per_hour', 12, 4)->default(0.0000);
                $table->decimal('total_daily_labor_cost', 14, 4)->default(0.0000);
                $table->decimal('machine_cost_per_hour', 12, 4)->default(0.0000);
                $table->decimal('total_daily_machine_cost', 14, 4)->default(0.0000);
                $table->decimal('total_daily_operating_cost', 14, 4)->default(0.0000);
                $table->decimal('total_operating_cost_per_hour', 12, 4)->default(0.0000);
                $table->decimal('unique_operating_hours', 8, 4)->default(0.0000);

                $table->longText('calculation_details')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'section_id'], 'pwcsc_tenant_sec_idx');
                $table->index(['batch_id'], 'pwcsc_batch_idx');
                $table->index(['work_center_calculation_id'], 'pwcsc_wc_calc_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_work_center_section_calculations');
        Schema::dropIfExists('production_work_center_calculations');
    }
};
