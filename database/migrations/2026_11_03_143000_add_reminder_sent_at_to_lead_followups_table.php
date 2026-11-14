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
        if (Schema::hasTable('lead_followups') && !Schema::hasColumn('lead_followups', 'reminder_sent_at')) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->timestamp('reminder_sent_at')->nullable()->after('status')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_followups') && Schema::hasColumn('lead_followups', 'reminder_sent_at')) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dropColumn('reminder_sent_at');
            });
        }
    }
};

