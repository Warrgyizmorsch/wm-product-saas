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
        if (Schema::hasTable('production_work_center_employees')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS pwce_wc_emp_unique');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS pwce_wc_sec_emp_unique ON production_work_center_employees (work_center_id, section_id, employee_id)');
                return;
            }

            Schema::table('production_work_center_employees', function (Blueprint $table) {
                // Drop foreign key that references work_center_id using pwce_wc_emp_unique index
                $table->dropForeign('pwce_wc_fk');

                // Drop legacy work-center-level unique constraint
                $table->dropUnique('pwce_wc_emp_unique');

                // Add section-scoped unique constraint
                $table->unique(['work_center_id', 'section_id', 'employee_id'], 'pwce_wc_sec_emp_unique');

                // Re-add foreign key
                $table->foreign('work_center_id', 'pwce_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_work_center_employees')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS pwce_wc_sec_emp_unique');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS pwce_wc_emp_unique ON production_work_center_employees (work_center_id, employee_id)');
                return;
            }

            Schema::table('production_work_center_employees', function (Blueprint $table) {
                $table->dropForeign('pwce_wc_fk');
                $table->dropUnique('pwce_wc_sec_emp_unique');
                $table->unique(['work_center_id', 'employee_id'], 'pwce_wc_emp_unique');
                $table->foreign('work_center_id', 'pwce_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();
            });
        }
    }
};
