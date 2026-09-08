<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_followups', 'crm_deal_id')) {
                $table->foreignId('crm_deal_id')->nullable()->after('lead_id')->constrained('crm_deals')->nullOnDelete();
            }
            if (!Schema::hasColumn('lead_followups', 'google_event_id')) {
                $table->string('google_event_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('lead_followups', 'google_meet_link')) {
                $table->string('google_meet_link')->nullable()->after('google_event_id');
            }
            if (!Schema::hasColumn('lead_followups', 'is_google_meet')) {
                $table->boolean('is_google_meet')->default(false)->after('google_meet_link');
            }
            if (!Schema::hasColumn('lead_followups', 'title')) {
                $table->string('title')->nullable()->after('type');
            }
            if (!Schema::hasColumn('lead_followups', 'duration_minutes')) {
                $table->integer('duration_minutes')->default(30)->after('title');
            }
            if (!Schema::hasColumn('lead_followups', 'guest_emails')) {
                $table->text('guest_emails')->nullable()->after('duration_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropForeign(['crm_deal_id']);
            $table->dropColumn(['crm_deal_id', 'google_event_id', 'google_meet_link', 'is_google_meet']);
        });
    }
};
