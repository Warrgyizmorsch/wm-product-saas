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
        if (!Schema::hasTable('payroll_payslips')) {
            Schema::create('payroll_payslips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('payroll_run_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('payroll_month', 7)->index();
                $table->string('status', 20)->default('locked')->index();
                $table->decimal('gross_earnings', 14, 2)->default(0.00);
                $table->decimal('total_deductions', 14, 2)->default(0.00);
                $table->decimal('net_payout', 14, 2)->default(0.00);
                $table->decimal('lop_days', 8, 2)->default(0.00);
                $table->decimal('paid_days', 8, 2)->default(0.00);
                $table->json('summary_data')->nullable();
                $table->json('items_data')->nullable();
                $table->timestamps();

                $table->unique(['payroll_run_id', 'employee_id'], 'payroll_payslips_run_emp_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_payslips');
    }
};

