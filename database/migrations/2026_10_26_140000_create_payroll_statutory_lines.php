<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PF ECR and ESIC monthly return.
 *  - employees.uan_number / esi_ip_number: the keys both returns are filed by.
 *  - payroll_statutory_lines: each employee's PF and ESI figures for a posted
 *    payroll run, kept as they were when the run was posted (payroll itself is
 *    recalculated on demand, so the return must not drift later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('uan_number', 12)->nullable();
            $table->string('esi_ip_number', 10)->nullable();
        });

        Schema::create('payroll_statutory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('payroll_run_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('payroll_month', 7); // YYYY-MM
            $table->string('employee_name');
            $table->string('uan_number', 12)->nullable();
            $table->string('esi_ip_number', 10)->nullable();

            $table->decimal('gross_wages', 15, 2)->default(0);
            $table->decimal('paid_days', 6, 2)->default(0);
            $table->decimal('ncp_days', 6, 2)->default(0); // non-contributing (LOP) days

            // PF (ECR 2.0)
            $table->decimal('epf_wages', 15, 2)->default(0);
            $table->decimal('eps_wages', 15, 2)->default(0);
            $table->decimal('edli_wages', 15, 2)->default(0);
            $table->decimal('epf_employee', 15, 2)->default(0);     // 12% from the employee
            $table->decimal('eps_employer', 15, 2)->default(0);     // 8.33% of EPS wages, to pension
            $table->decimal('epf_employer_diff', 15, 2)->default(0); // employer's 12% less EPS

            // ESI
            $table->decimal('esi_wages', 15, 2)->default(0);
            $table->decimal('esi_employee', 15, 2)->default(0);
            $table->decimal('esi_employer', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_run_id', 'employee_id'], 'psl_run_employee_unique');
            $table->index(['tenant_id', 'payroll_month'], 'psl_tenant_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_statutory_lines');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['uan_number', 'esi_ip_number']);
        });
    }
};
