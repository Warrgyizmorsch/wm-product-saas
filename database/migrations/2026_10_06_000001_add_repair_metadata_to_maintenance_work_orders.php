<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_maintenance_work_orders', 'repair_hours')) {
                $table->decimal('repair_hours', 8, 2)->default(0.00)->after('labor_hours');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'repair_cost')) {
                $table->decimal('repair_cost', 12, 2)->default(0.00)->after('labor_cost');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'mechanic_type')) {
                $table->enum('mechanic_type', ['inhouse', 'external', 'both'])->default('inhouse')->after('repair_cost');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'external_mechanic_cost')) {
                $table->decimal('external_mechanic_cost', 12, 2)->default(0.00)->after('mechanic_type');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'internal_mechanic_cost')) {
                $table->decimal('internal_mechanic_cost', 12, 2)->default(0.00)->after('external_mechanic_cost');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'repair_log')) {
                $table->json('repair_log')->nullable()->after('spare_parts_cost');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'scrap_machine')) {
                $table->boolean('scrap_machine')->default(false)->after('repair_log');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'scrap_value')) {
                $table->decimal('scrap_value', 12, 2)->default(0.00)->after('scrap_machine');
            }

            if (!Schema::hasColumn('production_maintenance_work_orders', 'decision_note')) {
                $table->text('decision_note')->nullable()->after('scrap_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('production_maintenance_work_orders', 'decision_note')) {
                $table->dropColumn('decision_note');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'scrap_value')) {
                $table->dropColumn('scrap_value');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'scrap_machine')) {
                $table->dropColumn('scrap_machine');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'repair_log')) {
                $table->dropColumn('repair_log');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'internal_mechanic_cost')) {
                $table->dropColumn('internal_mechanic_cost');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'external_mechanic_cost')) {
                $table->dropColumn('external_mechanic_cost');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'mechanic_type')) {
                $table->dropColumn('mechanic_type');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'repair_cost')) {
                $table->dropColumn('repair_cost');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'repair_hours')) {
                $table->dropColumn('repair_hours');
            }
        });
    }
};
