<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_maintenance_work_orders', 'mechanic_cost')) {
                $table->decimal('mechanic_cost', 12, 2)->default(0.00)->after('internal_mechanic_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'additional_cost')) {
                $table->decimal('additional_cost', 12, 2)->default(0.00)->after('spare_parts_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'external_parts_purchased')) {
                $table->boolean('external_parts_purchased')->default(false)->after('additional_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'was_machine_scraped')) {
                $table->boolean('was_machine_scraped')->default(false)->after('scrap_machine');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('production_maintenance_work_orders', 'was_machine_scraped')) {
                $table->dropColumn('was_machine_scraped');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'external_parts_purchased')) {
                $table->dropColumn('external_parts_purchased');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'additional_cost')) {
                $table->dropColumn('additional_cost');
            }
            if (Schema::hasColumn('production_maintenance_work_orders', 'mechanic_cost')) {
                $table->dropColumn('mechanic_cost');
            }
        });
    }
};
