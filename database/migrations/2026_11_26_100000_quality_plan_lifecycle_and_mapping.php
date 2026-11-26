<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Control rework (docs/production_quality_rules.md):
 *  - QR-02/07/08/11: plan scope Section, locked status flow, automatic versions grouped by plan_number / root_plan_id
 *  - QR-06/10: parameter "basis" for Pass/Fail rows, soft-deleted parameters, 4-decimal limits (no rounding)
 *  - QR-07: approval audit log
 *  - QR-15/16: routing operation → quality plans mapping (with mandatory flag)
 *  - QR-17: order operation snapshot of the plans (routing / sub_assembly source)
 *  - QR-16: one inspection may cover several plans
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_quality_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('production_quality_plans', 'plan_number')) {
                $table->string('plan_number', 30)->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('production_quality_plans', 'root_plan_id')) {
                $table->unsignedBigInteger('root_plan_id')->nullable()->after('plan_number');
            }
            if (!Schema::hasColumn('production_quality_plans', 'source_plan_id')) {
                $table->unsignedBigInteger('source_plan_id')->nullable()->after('root_plan_id');
            }
            if (!Schema::hasColumn('production_quality_plans', 'section_id')) {
                $table->unsignedBigInteger('section_id')->nullable()->after('work_center_id');
            }
            if (!Schema::hasColumn('production_quality_plans', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('production_quality_plans', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('production_quality_plans', 'historical_at')) {
                $table->timestamp('historical_at')->nullable()->after('approved_at');
            }
        });

        Schema::table('production_quality_plans', function (Blueprint $table) {
            $table->index(['tenant_id', 'root_plan_id'], 'pqp_tenant_root_idx');
            $table->index(['tenant_id', 'name', 'version'], 'pqp_tenant_name_version_idx');
            $table->index(['tenant_id', 'section_id'], 'pqp_tenant_section_idx');
        });

        Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
            if (!Schema::hasColumn('production_quality_plan_parameters', 'basis')) {
                $table->text('basis')->nullable()->after('unit_of_measure');
            }
            if (!Schema::hasColumn('production_quality_plan_parameters', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_mandatory');
            }
            if (!Schema::hasColumn('production_quality_plan_parameters', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // QP-18 / no-rounding rule: keep up to 4 decimals on limits and recorded readings
        Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
            $table->decimal('min_value', 18, 4)->nullable()->change();
            $table->decimal('max_value', 18, 4)->nullable()->change();
        });
        Schema::table('production_quality_inspection_results', function (Blueprint $table) {
            $table->decimal('recorded_value_numeric', 18, 4)->nullable()->change();
        });

        // QR-14: no more auto-created empty plans — a quantity-only QC (operation without a plan) has no plan
        Schema::table('production_quality_inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('quality_plan_id')->nullable()->change();
        });

        if (!Schema::hasTable('production_quality_plan_approvals')) {
            Schema::create('production_quality_plan_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('quality_plan_id');
                $table->foreign('quality_plan_id', 'pqpa_plan_fk')->references('id')->on('production_quality_plans')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id', 'pqpa_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->string('action', 40);
                $table->text('comments')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['tenant_id', 'quality_plan_id'], 'pqpa_tenant_plan_idx');
            });
        }

        if (!Schema::hasTable('production_routing_operation_quality_plans')) {
            Schema::create('production_routing_operation_quality_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('routing_operation_id');
                $table->foreign('routing_operation_id', 'proqp_op_fk')->references('id')->on('production_routing_operations')->cascadeOnDelete();
                $table->unsignedBigInteger('quality_plan_id');
                $table->foreign('quality_plan_id', 'proqp_plan_fk')->references('id')->on('production_quality_plans')->cascadeOnDelete();
                $table->boolean('is_mandatory')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['routing_operation_id', 'quality_plan_id'], 'proqp_op_plan_unique');
            });
        }

        if (!Schema::hasTable('production_order_operation_quality_plans')) {
            Schema::create('production_order_operation_quality_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('production_order_operation_id');
                $table->foreign('production_order_operation_id', 'pooqp_op_fk')->references('id')->on('production_order_operations')->cascadeOnDelete();
                $table->unsignedBigInteger('quality_plan_id');
                $table->foreign('quality_plan_id', 'pooqp_plan_fk')->references('id')->on('production_quality_plans')->cascadeOnDelete();
                $table->boolean('is_mandatory')->default(true);
                $table->string('source', 20)->default('routing'); // routing | sub_assembly
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['production_order_operation_id', 'quality_plan_id'], 'pooqp_op_plan_unique');
            });
        }

        if (!Schema::hasTable('production_quality_inspection_plans')) {
            Schema::create('production_quality_inspection_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('quality_inspection_id');
                $table->foreign('quality_inspection_id', 'pqip_insp_fk')->references('id')->on('production_quality_inspections')->cascadeOnDelete();
                $table->unsignedBigInteger('quality_plan_id');
                $table->foreign('quality_plan_id', 'pqip_plan_fk')->references('id')->on('production_quality_plans')->cascadeOnDelete();
                $table->boolean('is_mandatory')->default(true);
                $table->timestamps();
                $table->unique(['quality_inspection_id', 'quality_plan_id'], 'pqip_insp_plan_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_quality_inspection_plans');
        Schema::dropIfExists('production_order_operation_quality_plans');
        Schema::dropIfExists('production_routing_operation_quality_plans');
        Schema::dropIfExists('production_quality_plan_approvals');

        Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
            if (Schema::hasColumn('production_quality_plan_parameters', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            foreach (['basis', 'sort_order'] as $col) {
                if (Schema::hasColumn('production_quality_plan_parameters', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('production_quality_plans', function (Blueprint $table) {
            $table->dropIndex('pqp_tenant_root_idx');
            $table->dropIndex('pqp_tenant_name_version_idx');
            $table->dropIndex('pqp_tenant_section_idx');
        });

        Schema::table('production_quality_plans', function (Blueprint $table) {
            foreach (['plan_number', 'root_plan_id', 'source_plan_id', 'section_id', 'submitted_by', 'submitted_at', 'historical_at'] as $col) {
                if (Schema::hasColumn('production_quality_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
