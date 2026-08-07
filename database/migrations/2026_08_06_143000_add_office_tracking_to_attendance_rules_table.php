<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds all new attendance rule columns:
     * - office_geofence     : require location coordinate capture for office web check-in
     * - office_tracking     : enable live location tracking during office shift
     * - office_tracking_minutes : how often to fetch location (office)
     * - wfh_tracking_minutes    : how often to fetch location (WFH)
     * - site_tracking_minutes   : how often to fetch location (On-Site)
     */
    public function up(): void
    {
        Schema::table('attendance_rules', function (Blueprint $table) {
            // Office Settings additions
            if (!Schema::hasColumn('attendance_rules', 'office_geofence')) {
                $table->boolean('office_geofence')->default(false)->after('office_web');
            }
            if (!Schema::hasColumn('attendance_rules', 'office_tracking')) {
                $table->boolean('office_tracking')->default(false)->after('office_radius');
            }
            if (!Schema::hasColumn('attendance_rules', 'office_tracking_minutes')) {
                $table->unsignedInteger('office_tracking_minutes')->default(15)->after('office_tracking');
            }

            // WFH Settings additions
            if (!Schema::hasColumn('attendance_rules', 'wfh_tracking_minutes')) {
                $table->unsignedInteger('wfh_tracking_minutes')->default(15)->after('wfh_tracking_meters');
            }

            // On-Site Settings additions
            if (!Schema::hasColumn('attendance_rules', 'site_tracking_minutes')) {
                $table->unsignedInteger('site_tracking_minutes')->default(15)->after('site_tracking_meters');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_rules', function (Blueprint $table) {
            $columns = [
                'site_tracking_minutes',
                'wfh_tracking_minutes',
                'office_tracking_minutes',
                'office_tracking',
                'office_geofence',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('attendance_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
