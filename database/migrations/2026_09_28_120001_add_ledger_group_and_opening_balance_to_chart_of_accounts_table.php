<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('chart_of_accounts', 'ledger_group_id')) {
                $table->foreignId('ledger_group_id')->nullable()->after('parent_id')
                    ->constrained('ledger_groups')->nullOnDelete();
                $table->index(['tenant_id', 'ledger_group_id']);
            }

            if (!Schema::hasColumn('chart_of_accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 18, 2)->default(0)->after('is_cash_or_bank');
                $table->string('opening_balance_type')->default('debit')->after('opening_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'ledger_group_id')) {
                $table->dropConstrainedForeignId('ledger_group_id');
            }

            if (Schema::hasColumn('chart_of_accounts', 'opening_balance')) {
                $table->dropColumn(['opening_balance', 'opening_balance_type']);
            }
        });
    }
};
