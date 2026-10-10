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
            if (!Schema::hasColumn('lead_followups', 'recording_url')) {
                $table->text('recording_url')->nullable();
            }
            if (!Schema::hasColumn('lead_followups', 'audio_duration')) {
                $table->integer('audio_duration')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            if (Schema::hasColumn('lead_followups', 'recording_url')) {
                $table->dropColumn('recording_url');
            }
            if (Schema::hasColumn('lead_followups', 'audio_duration')) {
                $table->dropColumn('audio_duration');
            }
        });
    }
};
