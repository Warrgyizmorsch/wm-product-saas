<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'office_radius',
        'office_tracking_minutes',
        'wfh_tracking_meters',
        'wfh_tracking_minutes',
        'site_tracking_meters',
        'site_tracking_minutes',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('attendance_rules')) {
            return;
        }

        Schema::table('attendance_rules', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('attendance_rules', $column)) {
                    $table->integer($column)->nullable()->default(null)->change();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_rules')) {
            return;
        }

        $defaults = [
            'office_radius' => 100,
            'office_tracking_minutes' => 15,
            'wfh_tracking_meters' => 50,
            'wfh_tracking_minutes' => 15,
            'site_tracking_meters' => 50,
            'site_tracking_minutes' => 15,
        ];

        foreach ($defaults as $column => $default) {
            if (Schema::hasColumn('attendance_rules', $column)) {
                DB::table('attendance_rules')->whereNull($column)->update([$column => $default]);
            }
        }

        Schema::table('attendance_rules', function (Blueprint $table) use ($defaults) {
            foreach ($defaults as $column => $default) {
                if (Schema::hasColumn('attendance_rules', $column)) {
                    $table->integer($column)->default($default)->nullable(false)->change();
                }
            }
        });
    }
};
