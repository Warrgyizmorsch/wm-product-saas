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
        // 1. Employee Probation Evaluations
        if (!Schema::hasTable('employee_probation_evaluations')) {
            Schema::create('employee_probation_evaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('evaluation_date');
                $table->unsignedTinyInteger('performance_rating')->default(3); // 1-5
                $table->unsignedTinyInteger('attendance_rating')->default(3); // 1-5
                $table->unsignedTinyInteger('culture_rating')->default(3); // 1-5
                $table->string('recommendation')->default('confirm'); // confirm, extend, terminate
                $table->integer('extension_days')->nullable();
                $table->date('new_probation_end_date')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status')->default('completed'); // pending, completed, rejected
                $table->timestamps();
            });
        }

        // 2. Employee Exits (Resignations & Involuntary Terminations)
        if (!Schema::hasTable('employee_exits')) {
            Schema::create('employee_exits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('separation_type')->default('resignation'); // resignation, termination, retirement, layoff, contract_end, absconding
                $table->date('resignation_date');
                $table->date('preferred_lwd')->nullable(); // Preferred Last Working Day
                $table->date('approved_lwd')->nullable(); // Final Approved Last Working Day
                $table->integer('notice_period_days')->default(30);
                $table->integer('notice_shortfall_days')->default(0);
                $table->string('notice_action')->default('serve'); // serve, recover, waive
                $table->string('reason_category')->nullable(); // Better Opportunity, Relocation, Personal, Health, Career Change, Compensation, Higher Studies, Involuntary
                $table->text('reason_details')->nullable();
                $table->string('status')->default('pending_manager'); // draft, pending_manager, pending_hr, approved, rejected, in_clearance, settled, cancelled
                $table->string('initiated_by')->default('employee'); // employee, hr, manager
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->text('exit_interview_notes')->nullable();
                $table->unsignedTinyInteger('exit_interview_rating')->nullable(); // 1-5 overall company experience
                $table->timestamps();
            });
        }

        // 3. Employee Exit Multi-Department Clearances (NOC)
        if (!Schema::hasTable('employee_exit_clearances')) {
            Schema::create('employee_exit_clearances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_exit_id')->constrained('employee_exits')->cascadeOnDelete();
                $table->string('department'); // it, admin, finance, hr, manager
                $table->string('item_name');
                $table->string('status')->default('pending'); // pending, cleared, waived, rejected
                $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('cleared_at')->nullable();
                $table->text('remarks')->nullable();
                $table->decimal('deduction_amount', 10, 2)->default(0.00);
                $table->timestamps();
            });
        }

        // 4. Employee Full & Final (FnF) Settlements
        if (!Schema::hasTable('employee_fnf_settlements')) {
            Schema::create('employee_fnf_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_exit_id')->constrained('employee_exits')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('calculation_date');
                $table->date('lwd');
                
                // Earnings breakdown
                $table->decimal('unpaid_salary_days', 6, 2)->default(0.00);
                $table->decimal('unpaid_salary_amount', 12, 2)->default(0.00);
                $table->decimal('leave_encashment_days', 6, 2)->default(0.00);
                $table->decimal('leave_encashment_amount', 12, 2)->default(0.00);
                $table->decimal('gratuity_amount', 12, 2)->default(0.00);
                $table->decimal('bonus_amount', 12, 2)->default(0.00);
                $table->decimal('other_earnings', 12, 2)->default(0.00);
                $table->decimal('total_earnings', 12, 2)->default(0.00);
                
                // Deductions breakdown
                $table->decimal('notice_shortfall_recovery', 12, 2)->default(0.00);
                $table->decimal('unsettled_advances_recovery', 12, 2)->default(0.00);
                $table->decimal('asset_damage_recovery', 12, 2)->default(0.00);
                $table->decimal('other_deductions', 12, 2)->default(0.00);
                $table->decimal('total_deductions', 12, 2)->default(0.00);
                
                // Net
                $table->decimal('net_payable_amount', 12, 2)->default(0.00);
                $table->string('status')->default('draft'); // draft, approved, paid
                $table->string('settlement_channel')->default('monthly_payroll'); // monthly_payroll, off_cycle
                $table->string('payment_method')->nullable(); // Bank Transfer, Cheque, UPI
                $table->string('payment_reference')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. Employee Exit Documents (Relieving Letter, Experience Letter, NOC Certificate)
        if (!Schema::hasTable('employee_exit_documents')) {
            Schema::create('employee_exit_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_exit_id')->constrained('employee_exits')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('document_type'); // relieving_letter, experience_certificate, noc_certificate
                $table->string('reference_number')->nullable();
                $table->date('issue_date');
                $table->string('file_path')->nullable();
                $table->json('content_data')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_exit_documents');
        Schema::dropIfExists('employee_fnf_settlements');
        Schema::dropIfExists('employee_exit_clearances');
        Schema::dropIfExists('employee_exits');
        Schema::dropIfExists('employee_probation_evaluations');
    }
};
