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
            if (!Schema::hasColumn('lead_followups', 'checkin_latitude')) {
                $table->decimal('checkin_latitude', 10, 7)->nullable()->after('location_address');
            }
            if (!Schema::hasColumn('lead_followups', 'checkin_longitude')) {
                $table->decimal('checkin_longitude', 10, 7)->nullable()->after('checkin_latitude');
            }
            if (!Schema::hasColumn('lead_followups', 'checkin_address')) {
                $table->text('checkin_address')->nullable()->after('checkin_longitude');
            }
            if (!Schema::hasColumn('lead_followups', 'checkin_distance_meters')) {
                $table->integer('checkin_distance_meters')->nullable()->after('checkin_address');
            }
            if (!Schema::hasColumn('lead_followups', 'checkin_status')) {
                $table->string('checkin_status', 100)->nullable()->after('checkin_distance_meters');
            }
            if (!Schema::hasColumn('lead_followups', 'checkin_at')) {
                $table->timestamp('checkin_at')->nullable()->after('checkin_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $cols = [
                'checkin_latitude',
                'checkin_longitude',
                'checkin_address',
                'checkin_distance_meters',
                'checkin_status',
                'checkin_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('lead_followups', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
