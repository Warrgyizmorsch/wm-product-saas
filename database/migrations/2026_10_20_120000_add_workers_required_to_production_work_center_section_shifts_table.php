<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_work_center_section_shifts', function (Blueprint $table) {
            $table->unsignedInteger('workers_required')->default(1)->after('shift_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE production_work_center_section_shifts s
                JOIN production_work_center_sections sec ON s.section_id = sec.id
                SET s.workers_required = COALESCE(sec.workers_required, 1)');
        } else {
            DB::statement('UPDATE production_work_center_section_shifts
                SET workers_required = COALESCE((
                    SELECT workers_required FROM production_work_center_sections
                    WHERE production_work_center_sections.id = production_work_center_section_shifts.section_id
                ), 1)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_section_shifts', function (Blueprint $table) {
            $table->dropColumn('workers_required');
        });
    }
};
