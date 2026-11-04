<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_advance_payments') && ! Schema::hasColumn('purchase_advance_payments', 'bank_account_id')) {
            Schema::table('purchase_advance_payments', function (Blueprint $table) {
                $table->foreignId('bank_account_id')->nullable()->after('payment_method')->constrained('chart_of_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_advance_payments') && Schema::hasColumn('purchase_advance_payments', 'bank_account_id')) {
            Schema::table('purchase_advance_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('bank_account_id');
            });
        }
    }
};
