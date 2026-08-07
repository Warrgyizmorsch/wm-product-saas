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
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'crm_account_id')) {
                $table->foreignId('crm_account_id')->nullable()->after('lead_owner_id')->constrained('crm_accounts')->onDelete('set null');
            }
            if (!Schema::hasColumn('leads', 'crm_contact_id')) {
                $table->foreignId('crm_contact_id')->nullable()->after('crm_account_id')->constrained('crm_contacts')->onDelete('set null');
            }
            if (!Schema::hasColumn('leads', 'crm_deal_id')) {
                $table->foreignId('crm_deal_id')->nullable()->after('crm_contact_id')->constrained('crm_deals')->onDelete('set null');
            }
            if (!Schema::hasColumn('leads', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['crm_account_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_deal_id']);
            $table->dropColumn(['crm_account_id', 'crm_contact_id', 'crm_deal_id', 'converted_at']);
        });
    }
};
