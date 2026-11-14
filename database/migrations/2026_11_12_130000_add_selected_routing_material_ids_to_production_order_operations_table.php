<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PO-IMPL-01 (PO-12): exact RoutingOperationMaterial ids chosen for each snapshotted order operation.
 * NULL on legacy rows → readers fall back to the primary (is_alternative = false) allocations of source_bom_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('production_order_operations', 'selected_routing_material_ids')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                $table->json('selected_routing_material_ids')->nullable()->after('routing_operation_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('production_order_operations', 'selected_routing_material_ids')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                $table->dropColumn('selected_routing_material_ids');
            });
        }
    }
};
