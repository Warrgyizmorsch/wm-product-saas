<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columns that were superseded by the new completion-flow redesign. */
    private array $obsoleteCols = [
        'labor_hours',
        'repair_hours',
        'labor_cost_rate',
        'labor_cost',
        'repair_cost',
        'mechanic_type',
        'external_mechanic_cost',
        'internal_mechanic_cost',
        'scrap_machine',
        'scrap_value',
    ];

    public function up(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            $existing = array_filter(
                $this->obsoleteCols,
                fn (string $col) => Schema::hasColumn('production_maintenance_work_orders', $col)
            );

            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('production_maintenance_work_orders', 'labor_hours')) {
                $table->decimal('labor_hours', 8, 2)->default(0.00)->after('checklist_json');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'labor_cost_rate')) {
                $table->decimal('labor_cost_rate', 10, 2)->default(0.00)->after('labor_hours');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'labor_cost')) {
                $table->decimal('labor_cost', 12, 2)->default(0.00)->after('labor_cost_rate');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'repair_hours')) {
                $table->decimal('repair_hours', 8, 2)->default(0.00)->after('labor_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'repair_cost')) {
                $table->decimal('repair_cost', 12, 2)->default(0.00)->after('repair_hours');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'mechanic_type')) {
                $table->string('mechanic_type', 20)->nullable()->after('repair_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'external_mechanic_cost')) {
                $table->decimal('external_mechanic_cost', 12, 2)->default(0.00)->after('mechanic_type');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'internal_mechanic_cost')) {
                $table->decimal('internal_mechanic_cost', 12, 2)->default(0.00)->after('external_mechanic_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'scrap_machine')) {
                $table->boolean('scrap_machine')->default(false)->after('internal_mechanic_cost');
            }
            if (!Schema::hasColumn('production_maintenance_work_orders', 'scrap_value')) {
                $table->decimal('scrap_value', 12, 2)->default(0.00)->after('scrap_machine');
            }
        });
    }
};
