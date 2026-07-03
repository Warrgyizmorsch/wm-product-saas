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
        if (!Schema::hasTable('attendance_penalties')) {
            Schema::create('attendance_penalties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('rule_type'); // no_attendance, late_arrival, under_hours, missing_logs
                $table->integer('grace_period_minutes')->default(0);
                $table->integer('threshold_count')->default(0);
                $table->string('penalty_action')->default('salary_deduction'); // leave_deduction, salary_deduction
                $table->unsignedBigInteger('leave_type_id')->nullable();
                $table->decimal('penalty_value', 4, 2)->default(0.00);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('employee_penalties')) {
            Schema::create('employee_penalties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->string('rule_type');
                $table->decimal('penalty_amount', 4, 2)->default(0.00);
                $table->string('status')->default('pending'); // pending, applied, waived
                $table->string('payroll_month'); // YYYY-MM
                $table->text('remarks')->nullable();
                $table->timestamps();

                // Assuming the employees table is named 'employees'
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_penalties');
        Schema::dropIfExists('attendance_penalties');
    }
};
