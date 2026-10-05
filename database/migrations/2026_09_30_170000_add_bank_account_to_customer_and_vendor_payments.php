<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which cash/bank ledger a customer receipt or vendor payment went through.
 * Until now the posting listeners always used 1020 "Bank Account" (or 1010
 * for cash), so money received into HDFC or ICICI landed in the wrong ledger
 * and could never be reconciled against that bank's statement. Null keeps
 * the old behaviour for existing rows and screens that don't ask.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_payments', 'vendor_payments'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'bank_account_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('bank_account_id')->nullable()->after('payment_method')->constrained('chart_of_accounts')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['customer_payments', 'vendor_payments'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'bank_account_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('bank_account_id');
                });
            }
        }
    }
};
