<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isNotSqlite = DB::getDriverName() !== 'sqlite';

        // 1. Add 2-tier allocation fields to production_routing_operation_materials (Tab 1: Operation-Level Allocations)
        Schema::table('production_routing_operation_materials', function (Blueprint $table) use ($isNotSqlite) {
            if (!Schema::hasColumn('production_routing_operation_materials', 'bom_item_id')) {
                if ($isNotSqlite) {
                    $table->foreignId('bom_item_id')
                        ->nullable()
                        ->after('bom_id')
                        ->constrained('production_bom_items')
                        ->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('bom_item_id')->nullable()->after('bom_id');
                }
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'child_bom_id')) {
                if ($isNotSqlite) {
                    $table->foreignId('child_bom_id')
                        ->nullable()
                        ->after('material_id')
                        ->constrained('production_boms')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('child_bom_id')->nullable()->after('material_id');
                }
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'is_alternative')) {
                $table->boolean('is_alternative')
                    ->default(false)
                    ->after('uom_id');
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'priority')) {
                $table->unsignedInteger('priority')
                    ->nullable()
                    ->after('is_alternative');
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'replaces_routing_material_id')) {
                $table->unsignedBigInteger('replaces_routing_material_id')
                    ->nullable()
                    ->after('priority');
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'material_scrap_percentage')) {
                $table->decimal('material_scrap_percentage', 5, 2)
                    ->default(0.00)
                    ->after('replaces_routing_material_id');
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'quantity_type')) {
                $table->string('quantity_type', 20)
                    ->default('fixed')
                    ->after('material_scrap_percentage');
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'formula')) {
                $table->string('formula', 255)
                    ->nullable()
                    ->after('quantity_type');
            }
            if (!Schema::hasColumn('production_routing_operation_materials', 'notes')) {
                $table->text('notes')
                    ->nullable()
                    ->after('sequence');
            }
        });

        // Add foreign key constraint for replaces_routing_material_id for non-SQLite
        if ($isNotSqlite) {
            try {
                Schema::table('production_routing_operation_materials', function (Blueprint $table) {
                    $table->foreign('replaces_routing_material_id', 'rom_replaces_fk')
                        ->references('id')
                        ->on('production_routing_operation_materials')
                        ->nullOnDelete();
                });
            } catch (\Exception $e) {
                // Already added or unsupported
            }
        }

        // 2. Add allocation_role to production_bom_items (Tab 2: Master Recipe Summary)
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (!Schema::hasColumn('production_bom_items', 'allocation_role')) {
                $table->string('allocation_role', 20)
                    ->default('primary')
                    ->after('uom_id');
            }
        });

        // 3. Migrate and consolidate existing production_bom_items and link to production_routing_operation_materials
        $boms = DB::table('production_boms')->select('id')->get();
        foreach ($boms as $bom) {
            $items = DB::table('production_bom_items')->where('bom_id', $bom->id)->get();
            $grouped = $items->groupBy('material_id');

            foreach ($grouped as $materialId => $groupItems) {
                $keeper = $groupItems->first();
                $totalQty = $groupItems->sum('quantity');

                $hasPrimary = $groupItems->contains(fn ($it) => empty($it->is_alternative));
                $hasAlt = $groupItems->contains(fn ($it) => !empty($it->is_alternative));

                $role = 'primary';
                if ($hasPrimary && $hasAlt) {
                    $role = 'mixed';
                } elseif ($hasAlt) {
                    $role = 'alternative';
                }

                // Update keeper row
                DB::table('production_bom_items')
                    ->where('id', $keeper->id)
                    ->update([
                        'quantity' => $totalQty,
                        'allocation_role' => $role,
                    ]);

                // Link routing operation materials to keeper bom_item_id and copy attributes
                foreach ($groupItems as $oldItem) {
                    DB::table('production_routing_operation_materials')
                        ->where('bom_id', $bom->id)
                        ->where('routing_operation_id', $oldItem->routing_operation_id)
                        ->where('material_id', $oldItem->material_id)
                        ->update([
                            'bom_item_id' => $keeper->id,
                            'child_bom_id' => $oldItem->child_bom_id,
                            'is_alternative' => (bool) $oldItem->is_alternative,
                            'priority' => !empty($oldItem->is_alternative) ? $oldItem->priority : null,
                            'material_scrap_percentage' => $oldItem->material_scrap_percentage ?? 0.00,
                            'quantity_type' => $oldItem->quantity_type ?? 'fixed',
                            'formula' => $oldItem->formula ?? null,
                            'notes' => $oldItem->notes ?? null,
                        ]);
                }

                // Delete non-keeper duplicate rows from production_bom_items
                $duplicateIds = $groupItems->slice(1)->pluck('id')->toArray();
                if (!empty($duplicateIds)) {
                    // Update any references before deleting
                    DB::table('production_routing_operation_materials')
                        ->whereIn('bom_item_id', $duplicateIds)
                        ->update(['bom_item_id' => $keeper->id]);

                    DB::table('production_bom_items')
                        ->whereIn('id', $duplicateIds)
                        ->delete();
                }
            }
        }

        // Any orphaned routing operation materials get matched by material_id
        $unlinkedOpMats = DB::table('production_routing_operation_materials')
            ->whereNull('bom_item_id')
            ->get();

        foreach ($unlinkedOpMats as $opMat) {
            $matchingBomItem = DB::table('production_bom_items')
                ->where('bom_id', $opMat->bom_id)
                ->where('material_id', $opMat->material_id)
                ->first();

            if ($matchingBomItem) {
                DB::table('production_routing_operation_materials')
                    ->where('id', $opMat->id)
                    ->update(['bom_item_id' => $matchingBomItem->id]);
            }
        }

        // 4. Drop redundant FKs and alter columns on production_bom_items to allow true master recipe storage
        if ($isNotSqlite) {
            Schema::table('production_bom_items', function (Blueprint $table) {
                try {
                    $table->dropForeign('production_bom_items_child_bom_id_foreign');
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign('production_bom_items_replaces_item_id_foreign');
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign('production_bom_items_routing_operation_id_foreign');
                } catch (\Exception $e) {}
            });
        }

        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->unsignedBigInteger('routing_operation_id')->nullable()->change();
            $table->unsignedBigInteger('child_bom_id')->nullable()->change();
            $table->unsignedBigInteger('replaces_item_id')->nullable()->change();
            $table->unsignedInteger('priority')->nullable()->change();
            $table->integer('sequence')->nullable()->change();
            $table->text('notes')->nullable()->change();

            // Unique index: one material can be listed only once per bom_id
            $table->unique(['bom_id', 'material_id'], 'production_bom_items_bom_material_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isNotSqlite = DB::getDriverName() !== 'sqlite';

        Schema::table('production_bom_items', function (Blueprint $table) {
            try {
                $table->dropUnique('production_bom_items_bom_material_unique');
            } catch (\Exception $e) {}

            if (Schema::hasColumn('production_bom_items', 'allocation_role')) {
                $table->dropColumn('allocation_role');
            }
        });

        Schema::table('production_routing_operation_materials', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                try {
                    $table->dropForeign('rom_replaces_fk');
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign(['child_bom_id']);
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign(['bom_item_id']);
                } catch (\Exception $e) {}
            }

            $table->dropColumn([
                'bom_item_id',
                'child_bom_id',
                'is_alternative',
                'priority',
                'replaces_routing_material_id',
                'material_scrap_percentage',
                'quantity_type',
                'formula',
                'notes',
            ]);
        });
    }
};
