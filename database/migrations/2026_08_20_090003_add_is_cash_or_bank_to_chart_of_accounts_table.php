<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_cash_or_bank')->default(false)->after('normal_balance');
            $table->index(['tenant_id', 'is_cash_or_bank']);
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_cash_or_bank']);
            $table->dropColumn('is_cash_or_bank');
        });
    }
};
