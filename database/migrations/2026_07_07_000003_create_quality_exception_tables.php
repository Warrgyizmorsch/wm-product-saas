<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. New Table: production_quality_plans
        Schema::create('production_quality_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->string('status')->default('draft'); // draft | submitted | approved | archived
            $table->string('type'); // product | product_category | process | work_center
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('work_center_id')->nullable();
            
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by', 'pqp_creator_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pqp_approver_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->string('esignature')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'type']);
        });

        // 2. New Table: production_quality_plan_parameters
        Schema::create('production_quality_plan_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('quality_plan_id');
            $table->foreign('quality_plan_id', 'pqpp_qp_fk')->references('id')->on('production_quality_plans')->cascadeOnDelete();

            $table->string('name');
            $table->string('type'); // numeric | pass_fail | text
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->string('sampling_type')->default('fixed'); // fixed | percentage | ansi | custom
            $table->decimal('sampling_value', 10, 2)->default(1.0);
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
        });

        // 3. New Table: production_quality_inspections
        Schema::create('production_quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('quality_plan_id');
            $table->foreign('quality_plan_id', 'pqi_qp_fk')->references('id')->on('production_quality_plans')->cascadeOnDelete();

            $table->string('stage'); // incoming | in_process | final
            $table->string('status')->default('draft'); // draft | submitted | approved | closed
            $table->string('result')->default('passed'); // passed | failed | partial
            
            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->foreign('production_order_id', 'pqi_order_fk')->references('id')->on('production_orders')->nullOnDelete();

            $table->unsignedBigInteger('production_order_operation_id')->nullable();
            $table->foreign('production_order_operation_id', 'pqi_op_fk')->references('id')->on('production_order_operations')->nullOnDelete();

            $table->unsignedBigInteger('machine_id')->nullable();
            $table->foreign('machine_id', 'pqi_mach_fk')->references('id')->on('production_machines')->nullOnDelete();

            $table->unsignedBigInteger('operator_id')->nullable();
            $table->foreign('operator_id', 'pqi_oper_fk')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('batch_id')->nullable();
            $table->foreign('batch_id', 'pqi_batch_fk')->references('id')->on('production_batches')->nullOnDelete();

            $table->unsignedBigInteger('serial_number_id')->nullable();
            $table->foreign('serial_number_id', 'pqi_serial_fk')->references('id')->on('production_serial_numbers')->nullOnDelete();

            $table->unsignedBigInteger('audited_by')->nullable();
            $table->foreign('audited_by', 'pqi_audit_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('audited_at')->nullable();
            $table->string('esignature')->nullable();
            $table->json('attachments_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'stage', 'status']);
        });

        // 4. New Table: production_quality_inspection_results
        Schema::create('production_quality_inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('quality_inspection_id');
            $table->foreign('quality_inspection_id', 'pqir_qi_fk')->references('id')->on('production_quality_inspections')->cascadeOnDelete();

            $table->unsignedBigInteger('quality_plan_parameter_id');
            $table->foreign('quality_plan_parameter_id', 'pqir_qpp_fk')->references('id')->on('production_quality_plan_parameters')->cascadeOnDelete();

            $table->decimal('recorded_value_numeric', 10, 2)->nullable();
            $table->text('recorded_value_text')->nullable();
            $table->boolean('recorded_value_pass')->nullable();
            $table->string('result')->default('passed'); // passed | failed
            $table->timestamps();
        });

        // 5. New Table: production_ncrs
        Schema::create('production_ncrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ncr_number')->unique();
            $table->string('category'); // material | process | machine | human_error
            $table->string('status')->default('open'); // open | under_review | disposition | closed
            $table->string('disposition_type')->nullable(); // use_as_is | scrap | rework | return_to_supplier
            
            $table->unsignedBigInteger('quality_inspection_id')->nullable();
            $table->foreign('quality_inspection_id', 'pncr_insp_fk')->references('id')->on('production_quality_inspections')->nullOnDelete();

            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->foreign('production_order_id', 'pncr_order_fk')->references('id')->on('production_orders')->nullOnDelete();

            $table->unsignedBigInteger('production_order_operation_id')->nullable();
            $table->foreign('production_order_operation_id', 'pncr_op_fk')->references('id')->on('production_order_operations')->nullOnDelete();

            $table->unsignedBigInteger('machine_id')->nullable();
            $table->foreign('machine_id', 'pncr_mach_fk')->references('id')->on('production_machines')->nullOnDelete();

            $table->unsignedBigInteger('operator_id')->nullable();
            $table->foreign('operator_id', 'pncr_oper_fk')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('batch_id')->nullable();
            $table->foreign('batch_id', 'pncr_batch_fk')->references('id')->on('production_batches')->nullOnDelete();

            $table->unsignedBigInteger('serial_number_id')->nullable();
            $table->foreign('serial_number_id', 'pncr_serial_fk')->references('id')->on('production_serial_numbers')->nullOnDelete();

            $table->text('description');
            $table->string('esignature_closed')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by', 'pncr_close_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'category']);
        });

        // 6. New Table: production_capas
        Schema::create('production_capas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('capa_number')->unique();
            
            $table->unsignedBigInteger('ncr_id')->nullable();
            $table->foreign('ncr_id', 'pcapa_ncr_fk')->references('id')->on('production_ncrs')->nullOnDelete();

            $table->string('status')->default('draft'); // draft | active | verified | closed
            $table->string('root_cause_category')->nullable(); // method | machine | man | material | environment
            $table->json('rca_analysis_json')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();

            $table->unsignedBigInteger('action_owner_id');
            $table->foreign('action_owner_id', 'pcapa_owner_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->date('target_date')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('effectiveness_review')->nullable();
            $table->string('esignature_closed')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by', 'pcapa_close_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // 7. New Table: production_rework_orders
        Schema::create('production_rework_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('rework_number')->unique();
            
            $table->unsignedBigInteger('ncr_id');
            $table->foreign('ncr_id', 'prw_ncr_fk')->references('id')->on('production_ncrs')->cascadeOnDelete();
            
            $table->unsignedBigInteger('original_production_order_id');
            $table->foreign('original_production_order_id', 'prw_orig_order_fk')->references('id')->on('production_orders')->cascadeOnDelete();

            $table->string('status')->default('draft'); // draft | scheduled | running | completed
            $table->decimal('cost_estimate', 10, 2)->default(0.00);
            $table->decimal('actual_cost', 10, 2)->default(0.00);
            $table->decimal('labor_hours_actual', 10, 2)->default(0.00);
            $table->decimal('machine_hours_actual', 10, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();
        });

        // 8. New Table: production_rework_operations
        Schema::create('production_rework_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('rework_order_id');
            $table->foreign('rework_order_id', 'prwo_rwo_fk')->references('id')->on('production_rework_orders')->cascadeOnDelete();

            $table->integer('sequence');
            $table->string('name');
            
            $table->unsignedBigInteger('work_center_id');
            $table->foreign('work_center_id', 'prwo_wc_fk')->references('id')->on('production_work_centers')->cascadeOnDelete();

            $table->unsignedBigInteger('machine_id')->nullable();
            $table->foreign('machine_id', 'prwo_mach_fk')->references('id')->on('production_machines')->nullOnDelete();

            $table->string('status')->default('waiting'); // waiting | running | completed
            $table->decimal('setup_time_actual', 10, 2)->default(0.00);
            $table->decimal('processing_time_actual', 10, 2)->default(0.00);
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->timestamps();
        });

        // 9. New Table: production_scrap_disposals
        Schema::create('production_scrap_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('ncr_id')->nullable();
            $table->foreign('ncr_id', 'psd_ncr_fk')->references('id')->on('production_ncrs')->nullOnDelete();

            $table->string('category'); // raw_material | finished_good | scrap_metal | chemical
            $table->string('reason_code');
            $table->decimal('quantity', 10, 2);
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->string('status')->default('pending_approval'); // pending_approval | approved | disposed
            $table->timestamp('disposed_at')->nullable();
            
            $table->unsignedBigInteger('disposed_by')->nullable();
            $table->foreign('disposed_by', 'psd_disp_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // 10. New Table: production_deviations
        Schema::create('production_deviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('deviation_number')->unique();
            $table->string('type'); // temporary | permanent | customer_waiver
            $table->text('description');
            $table->date('expiration_date')->nullable();
            $table->decimal('expiration_quantity', 10, 2)->nullable();
            $table->string('status')->default('draft'); // draft | submitted | approved | expired

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pdev_appr_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->string('esignature')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_deviations');
        Schema::dropIfExists('production_scrap_disposals');
        Schema::dropIfExists('production_rework_operations');
        Schema::dropIfExists('production_rework_orders');
        Schema::dropIfExists('production_capas');
        Schema::dropIfExists('production_ncrs');
        Schema::dropIfExists('production_quality_inspection_results');
        Schema::dropIfExists('production_quality_inspections');
        Schema::dropIfExists('production_quality_plan_parameters');
        Schema::dropIfExists('production_quality_plans');
    }
};
