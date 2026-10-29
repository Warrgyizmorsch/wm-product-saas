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
        Schema::table('production_work_center_calculations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_work_center_calculations', 'direct_staff_daily_labor_cost')) {
                $table->decimal('direct_staff_daily_labor_cost', 14, 4)->default(0.0000)->after('total_daily_labor_cost');
            }
            if (!Schema::hasColumn('production_work_center_calculations', 'cost_per_unit')) {
                $table->decimal('cost_per_unit', 12, 4)->default(0.0000)->after('total_cost_per_hour');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_calculations', function (Blueprint $table) {
            $columns = [
                'direct_staff_daily_labor_cost',
                'cost_per_unit',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('production_work_center_calculations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

