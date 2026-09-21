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
        Schema::create('expense_approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('business_unit_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('approval_type')->default('1_level'); // 1_level, 2_level, conditional_threshold
            $table->string('first_approver')->default('reporting_manager'); // reporting_manager, department_head, hr_admin
            $table->string('second_approver')->default('finance_manager'); // finance_manager, hr_admin, department_head
            $table->decimal('amount_threshold_for_2_level', 10, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_approval_workflows');
    }
};
