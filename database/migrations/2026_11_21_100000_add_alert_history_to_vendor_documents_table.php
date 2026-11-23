<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FRS 2.3 / C-19: remembers which expiry reminders were already sent for a vendor document,
     * so the daily alert command notifies once per reminder day and once on expiry.
     */
    public function up(): void
    {
        if (Schema::hasTable('vendor_documents') && ! Schema::hasColumn('vendor_documents', 'alert_history')) {
            Schema::table('vendor_documents', function (Blueprint $table) {
                $table->json('alert_history')->nullable()->after('reminder_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vendor_documents', 'alert_history')) {
            Schema::table('vendor_documents', function (Blueprint $table) {
                $table->dropColumn('alert_history');
            });
        }
    }
};
