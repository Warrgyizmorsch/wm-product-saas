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
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'alert_history')) {
                $table->json('alert_history')->nullable()->after('signature_metadata');
            }
            if (!Schema::hasColumn('documents', 'last_expiry_alert_at')) {
                $table->timestamp('last_expiry_alert_at')->nullable()->after('alert_history');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'last_expiry_alert_at')) {
                $table->dropColumn('last_expiry_alert_at');
            }
            if (Schema::hasColumn('documents', 'alert_history')) {
                $table->dropColumn('alert_history');
            }
        });
    }
};

