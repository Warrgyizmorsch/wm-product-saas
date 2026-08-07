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
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'crm_account_id')) {
                $table->foreignId('crm_account_id')->nullable()->after('quotation_number')->constrained('crm_accounts')->onDelete('set null');
            }
            if (!Schema::hasColumn('quotations', 'crm_deal_id')) {
                $table->foreignId('crm_deal_id')->nullable()->after('crm_account_id')->constrained('crm_deals')->onDelete('set null');
            }
            if (!Schema::hasColumn('quotations', 'revision_number')) {
                $table->integer('revision_number')->default(1)->after('quotation_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['crm_account_id']);
            $table->dropForeign(['crm_deal_id']);
            $table->dropColumn(['crm_account_id', 'crm_deal_id', 'revision_number']);
        });
    }
};
