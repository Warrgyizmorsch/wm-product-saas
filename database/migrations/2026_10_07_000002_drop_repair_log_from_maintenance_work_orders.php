<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('production_maintenance_work_orders', 'repair_log')) {
                $table->dropColumn('repair_log');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_maintenance_work_orders', 'repair_log')) {
                $table->json('repair_log')->nullable()->after('spare_parts_cost');
            }
        });
    }
};
