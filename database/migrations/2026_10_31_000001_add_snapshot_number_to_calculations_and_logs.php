<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add snapshot_number to production_work_center_calculations
        if (Schema::hasTable('production_work_center_calculations')) {
            Schema::table('production_work_center_calculations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_center_calculations', 'snapshot_number')) {
                    $table->unsignedInteger('snapshot_number')->nullable()->after('work_center_id')->index('pwc_snapshot_num_idx');
                }
            });
        }

        // 2. Add snapshot_number to production_work_center_section_calculations
        if (Schema::hasTable('production_work_center_section_calculations')) {
            Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_center_section_calculations', 'snapshot_number')) {
                    $table->unsignedInteger('snapshot_number')->nullable()->after('section_id')->index('pwsc_snapshot_num_idx');
                }
            });
        }

        // 3. Add snapshot_number to production_work_center_activity_logs
        if (Schema::hasTable('production_work_center_activity_logs')) {
            Schema::table('production_work_center_activity_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_center_activity_logs', 'snapshot_number')) {
                    $table->unsignedInteger('snapshot_number')->nullable()->after('snapshot_id')->index('pwal_snapshot_num_idx');
                }
            });
        }

        // 4. Backfill Work Center Calculations snapshot_number (scoped per work_center_id)
        if (Schema::hasTable('production_work_center_calculations')) {
            $wcGroups = DB::table('production_work_center_calculations')
                ->select('work_center_id')
                ->distinct()
                ->pluck('work_center_id');

            foreach ($wcGroups as $wcId) {
                $calcs = DB::table('production_work_center_calculations')
                    ->where('work_center_id', $wcId)
                    ->orderBy('id', 'asc')
                    ->get(['id']);

                $num = 1;
                foreach ($calcs as $calc) {
                    DB::table('production_work_center_calculations')
                        ->where('id', $calc->id)
                        ->update(['snapshot_number' => $num++]);
                }
            }
        }

        // 5. Backfill Section Calculations snapshot_number (scoped per section_id)
        if (Schema::hasTable('production_work_center_section_calculations')) {
            $secGroups = DB::table('production_work_center_section_calculations')
                ->select('section_id')
                ->distinct()
                ->pluck('section_id');

            foreach ($secGroups as $secId) {
                $calcs = DB::table('production_work_center_section_calculations')
                    ->where('section_id', $secId)
                    ->orderBy('id', 'asc')
                    ->get(['id']);

                $num = 1;
                foreach ($calcs as $calc) {
                    DB::table('production_work_center_section_calculations')
                        ->where('id', $calc->id)
                        ->update(['snapshot_number' => $num++]);
                }
            }
        }

        // 6. Backfill Activity Logs snapshot_number & update description
        if (Schema::hasTable('production_work_center_activity_logs')) {
            $logs = DB::table('production_work_center_activity_logs')->get();

            foreach ($logs as $log) {
                $snapNum = null;
                if ($log->calculation_type === 'section') {
                    $snapNum = DB::table('production_work_center_section_calculations')
                        ->where('id', $log->snapshot_id)
                        ->value('snapshot_number');
                } elseif ($log->calculation_type === 'work_center') {
                    $snapNum = DB::table('production_work_center_calculations')
                        ->where('id', $log->snapshot_id)
                        ->value('snapshot_number');
                }

                if ($snapNum !== null) {
                    $desc = preg_replace('/snapshot #\d+/i', "snapshot #{$snapNum}", $log->description);
                    DB::table('production_work_center_activity_logs')
                        ->where('id', $log->id)
                        ->update([
                            'snapshot_number' => $snapNum,
                            'description'     => $desc,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_work_center_activity_logs') && Schema::hasColumn('production_work_center_activity_logs', 'snapshot_number')) {
            Schema::table('production_work_center_activity_logs', function (Blueprint $table) {
                $table->dropColumn('snapshot_number');
            });
        }

        if (Schema::hasTable('production_work_center_section_calculations') && Schema::hasColumn('production_work_center_section_calculations', 'snapshot_number')) {
            Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
                $table->dropColumn('snapshot_number');
            });
        }

        if (Schema::hasTable('production_work_center_calculations') && Schema::hasColumn('production_work_center_calculations', 'snapshot_number')) {
            Schema::table('production_work_center_calculations', function (Blueprint $table) {
                $table->dropColumn('snapshot_number');
            });
        }
    }
};
