<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BM-IMPL-04: Persist gross_quantity on operation allocations and backfill from net qty + scrap %.
     * BM-IMPL-09: Drop parameterized BOM formula columns (quantity_type, formula).
     */
    public function up(): void
    {
        if (Schema::hasTable('production_routing_operation_materials')) {
            Schema::table('production_routing_operation_materials', function (Blueprint $table) {
                if (! Schema::hasColumn('production_routing_operation_materials', 'gross_quantity')) {
                    $table->decimal('gross_quantity', 15, 4)
                        ->default(0)
                        ->after('material_scrap_percentage');
                }
            });

            DB::table('production_routing_operation_materials')->update([
                'gross_quantity' => DB::raw('ROUND(quantity * (1 + COALESCE(material_scrap_percentage, 0) / 100.0), 4)'),
            ]);

            $dropRomCols = array_values(array_filter(
                ['quantity_type', 'formula'],
                fn (string $col): bool => Schema::hasColumn('production_routing_operation_materials', $col)
            ));
            if ($dropRomCols !== []) {
                Schema::table('production_routing_operation_materials', function (Blueprint $table) use ($dropRomCols) {
                    $table->dropColumn($dropRomCols);
                });
            }
        }

        if (Schema::hasTable('production_bom_items')) {
            $dropItemCols = array_values(array_filter(
                ['quantity_type', 'formula'],
                fn (string $col): bool => Schema::hasColumn('production_bom_items', $col)
            ));
            if ($dropItemCols !== []) {
                Schema::table('production_bom_items', function (Blueprint $table) use ($dropItemCols) {
                    $table->dropColumn($dropItemCols);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_bom_items')) {
            Schema::table('production_bom_items', function (Blueprint $table) {
                if (! Schema::hasColumn('production_bom_items', 'quantity_type')) {
                    $table->string('quantity_type', 20)->default('fixed')->after('quantity');
                }
                if (! Schema::hasColumn('production_bom_items', 'formula')) {
                    $table->string('formula', 255)->nullable()->after('quantity_type');
                }
            });
        }

        if (Schema::hasTable('production_routing_operation_materials')) {
            Schema::table('production_routing_operation_materials', function (Blueprint $table) {
                if (! Schema::hasColumn('production_routing_operation_materials', 'quantity_type')) {
                    $table->string('quantity_type', 20)->default('fixed')->after('material_scrap_percentage');
                }
                if (! Schema::hasColumn('production_routing_operation_materials', 'formula')) {
                    $table->string('formula', 255)->nullable()->after('quantity_type');
                }
                if (Schema::hasColumn('production_routing_operation_materials', 'gross_quantity')) {
                    $table->dropColumn('gross_quantity');
                }
            });
        }
    }
};

