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
        if (DB::getDriverName() === 'sqlite') {
            // SQLite engine forbids dropping columns involved in foreign keys via ALTER TABLE.
            // Recreate table cleanly following standard SQLite table recreation pattern.
            Schema::disableForeignKeyConstraints();

            Schema::create('production_bom_items_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bom_id')->constrained('production_boms')->cascadeOnDelete();
                $table->foreignId('material_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('quantity', 12, 4);
                $table->string('quantity_type', 20)->default('fixed');
                $table->string('formula', 255)->nullable();
                $table->foreignId('uom_id')->constrained('uoms')->cascadeOnDelete();
                $table->string('allocation_role', 20)->default('primary');
                $table->decimal('material_scrap_percentage', 5, 2)->default(0.00);
                $table->timestamps();
            });

            DB::statement('INSERT INTO production_bom_items_new (id, tenant_id, bom_id, material_id, quantity, quantity_type, formula, uom_id, allocation_role, material_scrap_percentage, created_at, updated_at) SELECT id, tenant_id, bom_id, material_id, quantity, quantity_type, formula, uom_id, allocation_role, material_scrap_percentage, created_at, updated_at FROM production_bom_items');

            Schema::drop('production_bom_items');
            Schema::rename('production_bom_items_new', 'production_bom_items');

            Schema::table('production_bom_items', function (Blueprint $table) {
                $table->unique(['bom_id', 'material_id'], 'production_bom_items_bom_material_unique');
            });

            Schema::enableForeignKeyConstraints();
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::table('production_bom_items', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'routing_operation_id',
                'child_bom_id',
                'replaces_item_id',
                'is_alternative',
                'priority',
                'sequence',
                'notes',
            ] as $col) {
                if (Schema::hasColumn('production_bom_items', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->unsignedBigInteger('routing_operation_id')->nullable()->after('material_id');
            $table->unsignedBigInteger('child_bom_id')->nullable()->after('routing_operation_id');
            $table->unsignedBigInteger('replaces_item_id')->nullable()->after('child_bom_id');
            $table->boolean('is_alternative')->default(false)->after('material_scrap_percentage');
            $table->unsignedInteger('priority')->nullable()->after('is_alternative');
            $table->integer('sequence')->nullable()->after('priority');
            $table->text('notes')->nullable()->after('sequence');
        });
    }
};
