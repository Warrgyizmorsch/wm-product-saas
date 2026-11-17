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
        if (Schema::hasTable('production_machines')) {
            Schema::table('production_machines', function (Blueprint $table) {
                if (!Schema::hasColumn('production_machines', 'energy_source')) {
                    $table->enum('energy_source', ['electricity', 'petrol', 'diesel', 'others'])
                        ->default('electricity')
                        ->after('capacity');
                }

                if (!Schema::hasColumn('production_machines', 'consumption_per_hour')) {
                    $table->decimal('consumption_per_hour', 10, 4)
                        ->nullable()
                        ->default(0.0000)
                        ->after('energy_source');
                }

                if (!Schema::hasColumn('production_machines', 'energy_rate_per_unit')) {
                    $table->decimal('energy_rate_per_unit', 10, 4)
                        ->nullable()
                        ->default(0.0000)
                        ->after('consumption_per_hour');
                }

                if (!Schema::hasColumn('production_machines', 'operating_cost_per_hour')) {
                    $table->decimal('operating_cost_per_hour', 12, 4)
                        ->default(0.0000)
                        ->after('energy_rate_per_unit');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_machines')) {
            Schema::table('production_machines', function (Blueprint $table) {
                $columns = [
                    'energy_source',
                    'consumption_per_hour',
                    'energy_rate_per_unit',
                    'operating_cost_per_hour',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('production_machines', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
