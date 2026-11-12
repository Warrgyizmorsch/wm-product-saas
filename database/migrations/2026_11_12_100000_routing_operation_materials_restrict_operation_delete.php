<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RT-IMPL-09 (RT-10 rule 6): BOM operation allocations must never be wiped silently. The FK from
 * production_routing_operation_materials.routing_operation_id was cascadeOnDelete — a hard delete of an operation
 * deleted every BOM allocation on it. Routing saves now soft-delete removed operations (no FK fires); this makes a
 * later hard delete fail instead of deleting BOM data.
 * SQLite (tests) keeps its original table definition — FK changes there need a table rebuild and routing code
 * never hard-deletes operations any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('production_routing_operation_materials', function (Blueprint $table) {
            $table->dropForeign('rom_routing_op_fk');
            $table->foreign('routing_operation_id', 'rom_routing_op_fk')
                ->references('id')
                ->on('production_routing_operations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('production_routing_operation_materials', function (Blueprint $table) {
            $table->dropForeign('rom_routing_op_fk');
            $table->foreign('routing_operation_id', 'rom_routing_op_fk')
                ->references('id')
                ->on('production_routing_operations')
                ->cascadeOnDelete();
        });
    }
};
