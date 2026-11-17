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
        // 1. Add routing_id to production_boms
        Schema::table('production_boms', function (Blueprint $table) {
            if (!Schema::hasColumn('production_boms', 'routing_id')) {
                $table->foreignId('routing_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('routings')
                    ->restrictOnDelete();
            }
        });

        // 2. Drop bom_id and material_quantity_mode from routings
        Schema::table('routings', function (Blueprint $table) {
            if (Schema::hasColumn('routings', 'bom_id')) {
                $table->dropForeign(['bom_id']);
                $table->dropColumn('bom_id');
            }
            if (Schema::hasColumn('routings', 'material_quantity_mode')) {
                $table->dropColumn('material_quantity_mode');
            }
        });

        // 3. Add routing_operation_id to production_bom_items
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (!Schema::hasColumn('production_bom_items', 'routing_operation_id')) {
                $table->foreignId('routing_operation_id')
                    ->nullable()
                    ->after('material_id')
                    ->constrained('production_routing_operations')
                    ->nullOnDelete();
            }
        });

        // 4. Add bom_id to production_routing_operation_materials
        Schema::table('production_routing_operation_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('production_routing_operation_materials', 'bom_id')) {
                $table->foreignId('bom_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('production_boms')
                    ->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 4. Drop bom_id from production_routing_operation_materials
        Schema::table('production_routing_operation_materials', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operation_materials', 'bom_id')) {
                $table->dropForeign(['bom_id']);
                $table->dropColumn('bom_id');
            }
        });

        // 3. Drop routing_operation_id from production_bom_items
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('production_bom_items', 'routing_operation_id')) {
                $table->dropForeign(['routing_operation_id']);
                $table->dropColumn('routing_operation_id');
            }
        });

        // 2. Re-add bom_id and material_quantity_mode to routings
        Schema::table('routings', function (Blueprint $table) {
            if (!Schema::hasColumn('routings', 'bom_id')) {
                $table->foreignId('bom_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('production_boms')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('routings', 'material_quantity_mode')) {
                $table->string('material_quantity_mode', 50)
                    ->default('per_unit')
                    ->after('bom_id');
            }
        });

        // 1. Drop routing_id from production_boms
        Schema::table('production_boms', function (Blueprint $table) {
            if (Schema::hasColumn('production_boms', 'routing_id')) {
                $table->dropForeign(['routing_id']);
                $table->dropColumn('routing_id');
            }
        });
    }
};

