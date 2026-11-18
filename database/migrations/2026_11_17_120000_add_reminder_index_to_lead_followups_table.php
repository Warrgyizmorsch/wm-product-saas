<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The every-minute reminder job filters lead_followups by status and
 * followup_date across all tenants; without this index it scans the table.
 */
return new class extends Migration
{
    private const INDEX = 'lead_followups_status_followup_date_index';

    public function up(): void
    {
        if (Schema::hasTable('lead_followups') && ! Schema::hasIndex('lead_followups', self::INDEX)) {
            Schema::table('lead_followups', fn (Blueprint $table) => $table->index(['status', 'followup_date'], self::INDEX));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_followups') && Schema::hasIndex('lead_followups', self::INDEX)) {
            Schema::table('lead_followups', fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }
    }
};
