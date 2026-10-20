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
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'opening_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 4)->default(0.0000)->change();
            });
        }

        if (Schema::hasTable('crm_accounts') && Schema::hasColumn('crm_accounts', 'credit_limit')) {
            Schema::table('crm_accounts', function (Blueprint $table) {
                $table->decimal('credit_limit', 15, 4)->default(0.0000)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'opening_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 2)->default(0.00)->change();
            });
        }

        if (Schema::hasTable('crm_accounts') && Schema::hasColumn('crm_accounts', 'credit_limit')) {
            Schema::table('crm_accounts', function (Blueprint $table) {
                $table->decimal('credit_limit', 15, 2)->default(0.00)->change();
            });
        }
    }
};
