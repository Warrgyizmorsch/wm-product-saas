<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('lead_followups') && !Schema::hasColumn('lead_followups', 'assigned_to_id')) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->foreignId('assigned_to_id')
                    ->nullable()
                    ->after('crm_deal_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('assigned_to_id');
            });

            // Backfill existing rows: default to lead_owner_id or deal owner_id or tagged_user_id
            try {
                // Backfill from leads
                DB::statement("
                    UPDATE lead_followups
                    SET assigned_to_id = (
                        SELECT leads.lead_owner_id 
                        FROM leads 
                        WHERE leads.id = lead_followups.lead_id
                    )
                    WHERE assigned_to_id IS NULL AND lead_id IS NOT NULL
                ");

                // Backfill from deals
                DB::statement("
                    UPDATE lead_followups
                    SET assigned_to_id = (
                        SELECT crm_deals.owner_id 
                        FROM crm_deals 
                        WHERE crm_deals.id = lead_followups.crm_deal_id
                    )
                    WHERE assigned_to_id IS NULL AND crm_deal_id IS NOT NULL
                ");

                // Fallback to tagged_user_id if still null
                DB::statement("
                    UPDATE lead_followups
                    SET assigned_to_id = tagged_user_id
                    WHERE assigned_to_id IS NULL AND tagged_user_id IS NOT NULL
                ");
            } catch (\Throwable $e) {
                // Ignore backfill query errors if sqlite dialect differs
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_followups') && Schema::hasColumn('lead_followups', 'assigned_to_id')) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dropForeign(['assigned_to_id']);
                $table->dropColumn('assigned_to_id');
            });
        }
    }
};

