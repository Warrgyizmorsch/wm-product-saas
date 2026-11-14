<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ledgers without an opening balance stored 'debit' by column default,
        // so liability/equity/income ledgers offered Debit in the edit form.
        DB::table('chart_of_accounts')
            ->where('opening_balance', 0)
            ->whereNotNull('normal_balance')
            ->whereColumn('opening_balance_type', '!=', 'normal_balance')
            ->update(['opening_balance_type' => DB::raw('normal_balance')]);
    }

    public function down(): void
    {
        // Data correction only.
    }
};
