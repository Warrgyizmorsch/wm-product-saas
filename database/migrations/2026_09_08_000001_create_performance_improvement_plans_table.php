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
        // 1. PIP Categories Master Table
        Schema::create('pip_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        // 2. PIP Policy Templates Master Table
        Schema::create('pip_policy_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->integer('duration_days')->default(30);
            $table->enum('checkin_frequency', ['weekly', 'biweekly', 'monthly'])->default('weekly');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        // 3. Performance Improvement Plans Main Table
        Schema::create('performance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('pip_number')->unique();
            
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('manager_id')->nullable()->index();
            $table->unsignedBigInteger('hr_representative_id')->nullable()->index();
            $table->unsignedBigInteger('pip_category_id')->nullable()->index();

            $table->string('reason_category')->nullable();
            $table->longText('reason_details')->nullable();

            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days')->default(30);
            $table->enum('checkin_frequency', ['weekly', 'biweekly', 'monthly'])->default('weekly');

            $table->enum('status', ['draft', 'active', 'under_review', 'completed_success', 'extended', 'role_reassigned', 'failed_terminated', 'cancelled'])->default('active');
            $table->enum('final_outcome', ['successful_completion', 'pip_extension', 'role_reassignment', 'termination'])->nullable();
            $table->longText('final_comments')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamp('employee_signed_at')->nullable();
            $table->timestamp('manager_signed_at')->nullable();
            $table->timestamp('hr_signed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('hr_representative_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('pip_category_id')->references('id')->on('pip_categories')->onDelete('set null');
        });

        // 4. PIP SMART Objectives Table
        Schema::create('pip_objectives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('pip_id')->index();
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('target_criteria')->nullable();
            $table->text('support_provided')->nullable();
            $table->decimal('weightage', 5, 2)->default(100.00);

            $table->enum('status', ['pending', 'in_progress', 'achieved', 'partially_achieved', 'not_achieved'])->default('pending');
            $table->text('manager_remarks')->nullable();
            $table->timestamps();

            $table->foreign('pip_id')->references('id')->on('performance_improvement_plans')->onDelete('cascade');
        });

        // 5. PIP Check-ins Table
        Schema::create('pip_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('pip_id')->index();
            $table->date('review_date');
            $table->unsignedBigInteger('reviewer_id')->nullable()->index();

            $table->enum('rating_status', ['on_track', 'off_track', 'at_risk', 'exceeding'])->default('on_track');
            $table->longText('manager_comments')->nullable();
            $table->longText('employee_comments')->nullable();
            $table->text('action_items')->nullable();

            $table->timestamps();

            $table->foreign('pip_id')->references('id')->on('performance_improvement_plans')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pip_checkins');
        Schema::dropIfExists('pip_objectives');
        Schema::dropIfExists('performance_improvement_plans');
        Schema::dropIfExists('pip_policy_templates');
        Schema::dropIfExists('pip_categories');
    }
};
