<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_routing_operations', 'subcontract_input_type')) {
                $table->string('subcontract_input_type', 50)->default('bom_raw_materials')
                    ->after('material_supply_type')
                    ->comment('bom_raw_materials | previous_operation_wip');
            }
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_order_operations', 'subcontract_input_type')) {
                $table->string('subcontract_input_type', 50)->default('bom_raw_materials')
                    ->after('material_supply_type')
                    ->comment('bom_raw_materials | previous_operation_wip');
            }
        });

        Schema::table('delivery_challans', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_challans', 'dispatched_wip_qty')) {
                $table->decimal('dispatched_wip_qty', 12, 4)->default(0.0000)
                    ->after('status');
            }
        });

        Schema::table('delivery_challan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_challan_items', 'production_batch_id')) {
                $table->unsignedBigInteger('production_batch_id')->nullable()->index()
                    ->after('product_id');
            }
            if (!Schema::hasColumn('delivery_challan_items', 'production_wip_id')) {
                $table->unsignedBigInteger('production_wip_id')->nullable()->index()
                    ->after('production_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_challan_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_challan_items', 'production_wip_id')) {
                $table->dropColumn('production_wip_id');
            }
            if (Schema::hasColumn('delivery_challan_items', 'production_batch_id')) {
                $table->dropColumn('production_batch_id');
            }
        });

        Schema::table('delivery_challans', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_challans', 'dispatched_wip_qty')) {
                $table->dropColumn('dispatched_wip_qty');
            }
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_operations', 'subcontract_input_type')) {
                $table->dropColumn('subcontract_input_type');
            }
        });

        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operations', 'subcontract_input_type')) {
                $table->dropColumn('subcontract_input_type');
            }
        });
    }
};
