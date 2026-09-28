<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Enterprise KRA & KPI Performance Management.
     */
    public function up(): void
    {
        // 1. KRA Categories Master Table (Focus Areas: Financial, Customer, Operational, People)
        if (!Schema::hasTable('kra_categories')) {
            Schema::create('kra_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('color')->default('#3b82f6');
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 2. Appraisal Cycles Main Table
        if (!Schema::hasTable('appraisal_cycles')) {
            Schema::create('appraisal_cycles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->enum('period_type', ['annual', 'semi_annual', 'quarterly', 'monthly'])->default('annual');
                $table->date('start_date');
                $table->date('end_date');
                $table->date('goal_setting_deadline')->nullable();
                $table->date('self_review_deadline')->nullable();
                $table->date('manager_review_deadline')->nullable();
                $table->enum('status', ['draft', 'goal_setting', 'in_progress', 'in_review', 'calibration', 'completed', 'archived'])->default('draft');
                $table->decimal('goal_weightage_percent', 5, 2)->default(70.00);
                $table->decimal('competency_weightage_percent', 5, 2)->default(30.00);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 3. KPI Master Library Table (Standard Reusable Metrics)
        if (!Schema::hasTable('kpi_masters')) {
            Schema::create('kpi_masters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('kra_category_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->enum('unit', ['percentage', 'currency', 'number', 'rating', 'boolean'])->default('percentage');
                $table->enum('calculation_type', ['higher_is_better', 'lower_is_better', 'milestone'])->default('higher_is_better');
                $table->decimal('default_target', 15, 2)->default(100.00);
                $table->decimal('default_weightage', 5, 2)->default(20.00);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('kra_category_id')->references('id')->on('kra_categories')->onDelete('set null');
            });
        }

        // 4. Role/Designation-Based KPI Templates
        if (!Schema::hasTable('kpi_templates')) {
            Schema::create('kpi_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('designation_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                $table->foreign('designation_id')->references('id')->on('designations')->onDelete('set null');
            });
        }

        // 5. KPI Template Items
        if (!Schema::hasTable('kpi_template_items')) {
            Schema::create('kpi_template_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('kpi_template_id')->index();
                $table->unsignedBigInteger('kra_category_id')->nullable()->index();
                $table->unsignedBigInteger('kpi_master_id')->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('unit', ['percentage', 'currency', 'number', 'rating', 'boolean'])->default('percentage');
                $table->enum('calculation_type', ['higher_is_better', 'lower_is_better', 'milestone'])->default('higher_is_better');
                $table->decimal('target', 15, 2)->default(100.00);
                $table->decimal('weightage', 5, 2)->default(20.00);
                $table->timestamps();

                $table->foreign('kpi_template_id')->references('id')->on('kpi_templates')->onDelete('cascade');
                $table->foreign('kra_category_id')->references('id')->on('kra_categories')->onDelete('set null');
                $table->foreign('kpi_master_id')->references('id')->on('kpi_masters')->onDelete('set null');
            });
        }

        // 6. Employee Goal Plans (The Active Scorecard / Appraisal per Employee per Cycle)
        if (!Schema::hasTable('employee_goal_plans')) {
            Schema::create('employee_goal_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('plan_number')->unique();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('appraisal_cycle_id')->index();
                $table->unsignedBigInteger('manager_id')->nullable()->index();
                $table->unsignedBigInteger('kpi_template_id')->nullable()->index();

                $table->enum('status', ['draft', 'submitted', 'approved', 'self_reviewed', 'manager_reviewed', 'calibrated', 'signed_off'])->default('draft');
                $table->decimal('total_weightage', 5, 2)->default(0.00);
                $table->decimal('goal_score', 6, 2)->nullable();
                $table->decimal('competency_score', 6, 2)->nullable();
                $table->decimal('final_score', 6, 2)->nullable();
                $table->string('final_grade')->nullable();
                $table->decimal('normalized_score', 6, 2)->nullable();

                $table->text('employee_comments')->nullable();
                $table->text('manager_comments')->nullable();
                $table->text('hr_comments')->nullable();
                $table->boolean('promotion_recommended')->default(false);
                $table->boolean('pip_triggered')->default(false);

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('self_reviewed_at')->nullable();
                $table->timestamp('manager_reviewed_at')->nullable();
                $table->timestamp('calibrated_at')->nullable();
                $table->timestamp('signed_off_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('appraisal_cycle_id')->references('id')->on('appraisal_cycles')->onDelete('cascade');
                $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
                $table->foreign('kpi_template_id')->references('id')->on('kpi_templates')->onDelete('set null');
            });
        }

        // 7. Individual Employee Goal Items (Specific KPI rows under Scorecard)
        if (!Schema::hasTable('employee_goal_items')) {
            Schema::create('employee_goal_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_goal_plan_id')->index();
                $table->unsignedBigInteger('kra_category_id')->nullable()->index();
                $table->unsignedBigInteger('kpi_master_id')->nullable()->index();

                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('unit', ['percentage', 'currency', 'number', 'rating', 'boolean'])->default('percentage');
                $table->enum('calculation_type', ['higher_is_better', 'lower_is_better', 'milestone'])->default('higher_is_better');
                $table->decimal('target', 15, 2)->default(100.00);
                $table->decimal('actual', 15, 2)->nullable();
                $table->decimal('weightage', 5, 2)->default(20.00);

                // Self Assessment
                $table->decimal('self_rating', 4, 2)->nullable();
                $table->decimal('self_score', 6, 2)->nullable();
                $table->text('self_comment')->nullable();

                // Manager Assessment
                $table->decimal('manager_rating', 4, 2)->nullable();
                $table->decimal('manager_score', 6, 2)->nullable();
                $table->text('manager_comment')->nullable();

                // Calculated Final Achievement Score
                $table->decimal('final_score', 6, 2)->nullable();
                $table->enum('status', ['pending', 'in_progress', 'achieved', 'partially_achieved', 'not_achieved'])->default('pending');

                $table->timestamps();

                $table->foreign('employee_goal_plan_id')->references('id')->on('employee_goal_plans')->onDelete('cascade');
                $table->foreign('kra_category_id')->references('id')->on('kra_categories')->onDelete('set null');
                $table->foreign('kpi_master_id')->references('id')->on('kpi_masters')->onDelete('set null');
            });
        }

        // 8. Goal Progress Tracking Logs (Mid-Cycle Check-ins)
        if (!Schema::hasTable('goal_progress_logs')) {
            Schema::create('goal_progress_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_goal_item_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('logged_by_id')->nullable()->index();

                $table->decimal('previous_value', 15, 2)->nullable();
                $table->decimal('current_value', 15, 2);
                $table->text('notes')->nullable();
                $table->string('attachment_path')->nullable();
                $table->timestamps();

                $table->foreign('employee_goal_item_id')->references('id')->on('employee_goal_items')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('logged_by_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 9. Appraisal Reviews (Formal Self, Manager, & Committee Evaluations)
        if (!Schema::hasTable('appraisal_reviews')) {
            Schema::create('appraisal_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_goal_plan_id')->index();
                $table->unsignedBigInteger('reviewer_id')->index();
                $table->enum('reviewer_role', ['self', 'manager', 'skip_level', 'hr_calibrator'])->default('manager');

                $table->decimal('overall_rating', 4, 2)->nullable();
                $table->decimal('overall_score', 6, 2)->nullable();
                $table->text('strengths')->nullable();
                $table->text('improvements')->nullable();
                $table->boolean('promotion_recommendation')->default(false);
                $table->decimal('increment_recommendation_percent', 5, 2)->nullable();
                $table->text('feedback_comments')->nullable();
                $table->enum('status', ['draft', 'submitted'])->default('draft');
                $table->timestamps();

                $table->foreign('employee_goal_plan_id')->references('id')->on('employee_goal_plans')->onDelete('cascade');
                $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_reviews');
        Schema::dropIfExists('goal_progress_logs');
        Schema::dropIfExists('employee_goal_items');
        Schema::dropIfExists('employee_goal_plans');
        Schema::dropIfExists('kpi_template_items');
        Schema::dropIfExists('kpi_templates');
        Schema::dropIfExists('kpi_masters');
        Schema::dropIfExists('appraisal_cycles');
        Schema::dropIfExists('kra_categories');
    }
};
