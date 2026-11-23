<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FRS 2.5 (C-05) / F04 LEAD-01: keep the originating channel of every lead
     * (web_form, api_import, manual, researched, referral, ...) so an externally
     * researched prospect stays distinguishable from an inbound enquiry.
     */
    public function up(): void
    {
        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'provenance')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('provenance', 40)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'provenance')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('provenance');
            });
        }
    }
};
