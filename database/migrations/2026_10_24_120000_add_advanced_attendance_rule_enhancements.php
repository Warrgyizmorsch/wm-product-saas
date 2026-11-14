<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_rules')) {
            return;
        }

        Schema::table('attendance_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_rules', 'pause_tracking_on_break')) {
                $table->boolean('pause_tracking_on_break')->default(false)->after('site_tracking_minutes');
            }
            if (!Schema::hasColumn('attendance_rules', 'office_break_geofence')) {
                $table->boolean('office_break_geofence')->default(false)->after('pause_tracking_on_break');
            }
            if (!Schema::hasColumn('attendance_rules', 'allow_multiple_punches')) {
                $table->boolean('allow_multiple_punches')->default(false)->after('office_break_geofence');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_rules')) {
            return;
        }

        Schema::table('attendance_rules', function (Blueprint $table) {
            $cols = ['pause_tracking_on_break', 'office_break_geofence', 'allow_multiple_punches'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('attendance_rules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
