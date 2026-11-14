<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_followups', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('lead_followups', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('lead_followups', 'location_address')) {
                $table->text('location_address')->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('lead_followups', 'selfie_path')) {
                $table->string('selfie_path', 500)->nullable()->after('location_address');
            }
            if (!Schema::hasColumn('lead_followups', 'location_verified_at')) {
                $table->timestamp('location_verified_at')->nullable()->after('selfie_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            if (Schema::hasColumn('lead_followups', 'location_verified_at')) {
                $table->dropColumn('location_verified_at');
            }
            if (Schema::hasColumn('lead_followups', 'selfie_path')) {
                $table->dropColumn('selfie_path');
            }
            if (Schema::hasColumn('lead_followups', 'location_address')) {
                $table->dropColumn('location_address');
            }
            if (Schema::hasColumn('lead_followups', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('lead_followups', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
