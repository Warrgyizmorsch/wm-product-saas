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
        if (Schema::hasTable('cash_advances') && !Schema::hasColumn('cash_advances', 'approved_amount')) {
            Schema::table('cash_advances', function (Blueprint $table) {
                $table->decimal('approved_amount', 10, 2)->nullable()->after('amount');
            });
        }

        if (Schema::hasTable('expense_reports')) {
            Schema::table('expense_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_reports', 'approved_amount')) {
                    $table->decimal('approved_amount', 10, 2)->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('expense_reports', 'approved_net_reimbursement')) {
                    $table->decimal('approved_net_reimbursement', 10, 2)->nullable()->after('net_reimbursement');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cash_advances') && Schema::hasColumn('cash_advances', 'approved_amount')) {
            Schema::table('cash_advances', function (Blueprint $table) {
                $table->dropColumn('approved_amount');
            });
        }

        if (Schema::hasTable('expense_reports')) {
            Schema::table('expense_reports', function (Blueprint $table) {
                $dropColumns = [];
                if (Schema::hasColumn('expense_reports', 'approved_amount')) {
                    $dropColumns[] = 'approved_amount';
                }
                if (Schema::hasColumn('expense_reports', 'approved_net_reimbursement')) {
                    $dropColumns[] = 'approved_net_reimbursement';
                }
                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }
};
