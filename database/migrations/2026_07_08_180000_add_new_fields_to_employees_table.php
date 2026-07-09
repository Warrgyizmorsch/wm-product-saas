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
        Schema::table('employees', function (Blueprint $table) {
             $table->unsignedBigInteger('pay_group_id')->nullable()->after('designation_id');
    $table->unsignedBigInteger('salary_structure_id')->nullable()->after('pay_group_id');
    $table->unsignedBigInteger('leave_plan_id')->nullable()->after('salary_structure_id');
            $table->unsignedBigInteger('attendance_penalty_id')->nullable()->after('leave_plan_id');
            $table->unsignedBigInteger('reporting_manager_id')->nullable()->after('attendance_penalty_id');
            $table->date('date_of_birth')->nullable()->after('date_of_joining');
            $table->date('probation_end_date')->nullable()->after('date_of_birth');
            $table->date('confirmation_date')->nullable()->after('probation_end_date');
            $table->unsignedBigInteger('shift_id')->nullable()->after('confirmation_date');
            $table->string('office_email')->nullable()->after('personal_email');
            
            // Bank details
            $table->string('bank_name')->nullable()->after('qualification');
            $table->string('account_number')->nullable()->after('bank_name');
            $table->string('ifsc_code')->nullable()->after('account_number');
            
            // Emergency details
            $table->string('emergency_contact_name')->nullable()->after('ifsc_code');
            $table->string('emergency_contact_number')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_number');

            // Foreign keys with nullOnDelete
            $table->foreign('reporting_manager_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('shift_id')->references('id')->on('production_shifts')->nullOnDelete();
            $table->foreign('attendance_penalty_id')->references('id')->on('attendance_penalties')->nullOnDelete(); 
             $table->foreign('pay_group_id')->references('id')->on('pay_groups')->nullOnDelete();
             $table->foreign('salary_structure_id')->references('id')->on('salary_structures')->nullOnDelete();
             $table->foreign('leave_plan_id')->references('id')->on('leave_plans')->nullOnDelete();
         });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['reporting_manager_id']);
            $table->dropForeign(['shift_id']);

            $table->dropColumn([
                'reporting_manager_id',
                'date_of_birth',
                'probation_end_date',
                'confirmation_date',
                'shift_id',
                'office_email',
                'bank_name',
                'account_number',
                'ifsc_code',
                'emergency_contact_name',
                'emergency_contact_number',
                'emergency_contact_relation'
            ]);
        });
    }
};
