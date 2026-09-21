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
        // 1. Add approval configuration fields to expense_policies
        Schema::table('expense_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_policies', 'approval_type')) {
                $table->string('approval_type')->default('1_level')->after('branch_id'); // 1_level, 2_level, conditional_threshold
            }
            if (!Schema::hasColumn('expense_policies', 'first_approver')) {
                $table->string('first_approver')->default('reporting_manager')->after('approval_type'); // reporting_manager, department_head, hr_admin
            }
            if (!Schema::hasColumn('expense_policies', 'second_approver')) {
                $table->string('second_approver')->default('finance_manager')->after('first_approver'); // finance_manager, hr_admin, department_head
            }
            if (!Schema::hasColumn('expense_policies', 'amount_threshold_for_2_level')) {
                $table->decimal('amount_threshold_for_2_level', 10, 2)->nullable()->after('second_approver');
            }
        });

        // Helper to add tracking columns
        $addTrackingColumns = function (string $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'approval_levels')) {
                    $table->unsignedTinyInteger('approval_levels')->default(1)->after('status');
                }
                if (!Schema::hasColumn($tableName, 'current_approval_level')) {
                    $table->unsignedTinyInteger('current_approval_level')->default(1)->after('approval_levels');
                }
                if (!Schema::hasColumn($tableName, 'l1_approved_by')) {
                    $table->foreignId('l1_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('current_approval_level');
                }
                if (!Schema::hasColumn($tableName, 'l1_approved_at')) {
                    $table->timestamp('l1_approved_at')->nullable()->after('l1_approved_by');
                }
                if (!Schema::hasColumn($tableName, 'l2_approved_by')) {
                    $table->foreignId('l2_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('l1_approved_at');
                }
                if (!Schema::hasColumn($tableName, 'l2_approved_at')) {
                    $table->timestamp('l2_approved_at')->nullable()->after('l2_approved_by');
                }
            });
        };

        // 2. Add tracking columns to expense_reports, travel_requests, and cash_advances
        $addTrackingColumns('expense_reports');
        $addTrackingColumns('travel_requests');
        $addTrackingColumns('cash_advances');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_policies', function (Blueprint $table) {
            $table->dropColumn(['approval_type', 'first_approver', 'second_approver', 'amount_threshold_for_2_level']);
        });

        $dropTrackingColumns = function (string $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(["{$tableName}_l1_approved_by_foreign"]);
                $table->dropForeign(["{$tableName}_l2_approved_by_foreign"]);
                $table->dropColumn(['approval_levels', 'current_approval_level', 'l1_approved_by', 'l1_approved_at', 'l2_approved_by', 'l2_approved_at']);
            });
        };

        $dropTrackingColumns('expense_reports');
        $dropTrackingColumns('travel_requests');
        $dropTrackingColumns('cash_advances');
    }
};
