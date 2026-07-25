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
            $table->foreignId('rescheduled_from_id')->nullable()->after('notes')->constrained('lead_followups')->nullOnDelete();
            $table->dateTime('original_followup_date')->nullable()->after('rescheduled_from_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_from_id']);
            $table->dropColumn(['rescheduled_from_id', 'original_followup_date']);
        });
    }
};
