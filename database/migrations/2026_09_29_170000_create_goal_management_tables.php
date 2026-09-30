<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Enterprise Goals & OKRs Management.
     */
    public function up(): void
    {
        // 1. Goal Cycles (Time Horizons / OKR Quarters)
        if (!Schema::hasTable('goal_cycles')) {
            Schema::create('goal_cycles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['planning', 'active', 'review', 'closed'])->default('active');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 2. Goal Categories (Strategic Pillars / Focus Areas)
        if (!Schema::hasTable('goal_categories')) {
            Schema::create('goal_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('color')->default('#3b82f6');
                $table->string('icon')->default('feather-target');
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 3. Goals / Objectives Master Table
        if (!Schema::hasTable('goals')) {
            Schema::create('goals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('goal_cycle_id')->nullable()->index();
                $table->unsignedBigInteger('goal_category_id')->nullable()->index();
                $table->enum('owner_type', ['company', 'department', 'employee'])->default('employee');
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->unsignedBigInteger('parent_goal_id')->nullable()->index(); // Cascading parent goal
                $table->enum('visibility', ['public', 'department', 'private'])->default('public');
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('weightage', 5, 2)->default(100.00);
                $table->decimal('progress_percentage', 5, 2)->default(0.00);
                $table->enum('health_status', ['on_track', 'at_risk', 'behind', 'completed', 'cancelled'])->default('on_track');
                $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('active');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('goal_cycle_id')->references('id')->on('goal_cycles')->onDelete('set null');
                $table->foreign('goal_category_id')->references('id')->on('goal_categories')->onDelete('set null');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
                $table->foreign('parent_goal_id')->references('id')->on('goals')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 4. Goal Key Results Table
        if (!Schema::hasTable('goal_key_results')) {
            Schema::create('goal_key_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('goal_id')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('metric_type', ['numeric', 'currency', 'percentage', 'boolean_milestone'])->default('percentage');
                $table->string('unit')->default('%');
                $table->decimal('start_value', 12, 2)->default(0.00);
                $table->decimal('target_value', 12, 2)->default(100.00);
                $table->decimal('current_value', 12, 2)->default(0.00);
                $table->decimal('weightage', 5, 2)->default(100.00);
                $table->decimal('progress_percentage', 5, 2)->default(0.00);
                $table->enum('health_status', ['on_track', 'at_risk', 'behind', 'completed'])->default('on_track');
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->date('due_date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('goal_id')->references('id')->on('goals')->onDelete('cascade');
                $table->foreign('owner_id')->references('id')->on('employees')->onDelete('set null');
            });
        }

        // 5. Goal Check-ins Table
        if (!Schema::hasTable('goal_check_ins')) {
            Schema::create('goal_check_ins', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('goal_id')->index();
                $table->unsignedBigInteger('goal_key_result_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->decimal('previous_value', 12, 2)->nullable();
                $table->decimal('new_value', 12, 2)->nullable();
                $table->decimal('previous_progress', 5, 2)->default(0.00);
                $table->decimal('new_progress', 5, 2)->default(0.00);
                $table->enum('health_status', ['on_track', 'at_risk', 'behind'])->default('on_track');
                $table->text('comment');
                $table->text('blockers')->nullable();
                $table->timestamp('check_in_date')->useCurrent();
                $table->timestamps();

                $table->foreign('goal_id')->references('id')->on('goals')->onDelete('cascade');
                $table->foreign('goal_key_result_id')->references('id')->on('goal_key_results')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_check_ins');
        Schema::dropIfExists('goal_key_results');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('goal_categories');
        Schema::dropIfExists('goal_cycles');
    }
};
