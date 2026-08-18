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
        Schema::table('lead_followups', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_followups', 'crm_deal_id')) {
                $table->foreignId('crm_deal_id')->nullable()->after('lead_id')->constrained('crm_deals')->onDelete('cascade');
            }
            $table->unsignedBigInteger('lead_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            if (Schema::hasColumn('lead_followups', 'crm_deal_id')) {
                $table->dropForeign(['crm_deal_id']);
                $table->dropColumn('crm_deal_id');
            }
            $table->unsignedBigInteger('lead_id')->nullable(false)->change();
        });
    }
};
