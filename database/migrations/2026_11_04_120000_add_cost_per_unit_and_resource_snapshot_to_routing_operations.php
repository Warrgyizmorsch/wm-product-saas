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
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_routing_operations', 'cost_per_unit')) {
                $table->decimal('cost_per_unit', 15, 4)
                    ->nullable()
                    ->after('machine_cost_rate')
                    ->comment('Calculated unit cost: (setup_time + processing_time) * (labor_rate + machine_rate) * yield_factor');
            }

            if (!Schema::hasColumn('production_routing_operations', 'resource_snapshot')) {
                $table->json('resource_snapshot')
                    ->nullable()
                    ->after('cost_per_unit')
                    ->comment('Immutable snapshot of resource spec at routing save (capacity, run_speed, machine_cost, operator_cost, operation_cost)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operations', 'resource_snapshot')) {
                $table->dropColumn('resource_snapshot');
            }
            if (Schema::hasColumn('production_routing_operations', 'cost_per_unit')) {
                $table->dropColumn('cost_per_unit');
            }
        });
    }
};

