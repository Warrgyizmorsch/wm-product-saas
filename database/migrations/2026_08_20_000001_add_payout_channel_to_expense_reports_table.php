<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expense_reports') && !Schema::hasColumn('expense_reports', 'payout_channel')) {
            Schema::table('expense_reports', function (Blueprint $table) {
                $table->string('payout_channel')->default('accounting')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expense_reports') && Schema::hasColumn('expense_reports', 'payout_channel')) {
            Schema::table('expense_reports', function (Blueprint $table) {
                $table->dropColumn('payout_channel');
            });
        }
    }
};
