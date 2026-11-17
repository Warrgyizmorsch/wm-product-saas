<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WC-IMPL-08 (WC-23 rule 5): Work Center cost per unit is NULL ("N/A") when
 * daily capacity is 0 but cost is being incurred. Old rows keep their stored 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('production_work_center_calculations', 'cost_per_unit')) {
            return;
        }

        Schema::table('production_work_center_calculations', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 12, 4)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('production_work_center_calculations', 'cost_per_unit')) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('production_work_center_calculations')
            ->whereNull('cost_per_unit')
            ->update(['cost_per_unit' => 0]);

        Schema::table('production_work_center_calculations', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 12, 4)->default(0.0000)->nullable(false)->change();
        });
    }
};
