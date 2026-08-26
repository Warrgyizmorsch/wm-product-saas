<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_order_operations', 'source_product_id')) {
                $table->foreignId('source_product_id')->nullable()->after('routing_operation_id')->constrained('products')->nullOnDelete();
            }
            if (!Schema::hasColumn('production_order_operations', 'source_bom_id')) {
                $table->foreignId('source_bom_id')->nullable()->after('source_product_id')->constrained('production_boms')->nullOnDelete();
            }
            if (!Schema::hasColumn('production_order_operations', 'source_routing_id')) {
                $table->foreignId('source_routing_id')->nullable()->after('source_bom_id')->constrained('routings')->nullOnDelete();
            }
            if (!Schema::hasColumn('production_order_operations', 'bom_level')) {
                $table->integer('bom_level')->default(1)->after('source_routing_id');
            }
            if (!Schema::hasColumn('production_order_operations', 'target_produced_qty')) {
                $table->decimal('target_produced_qty', 12, 4)->nullable()->after('bom_level');
            }
            if (!Schema::hasColumn('production_order_operations', 'is_intermediate')) {
                $table->boolean('is_intermediate')->default(false)->after('target_produced_qty');
            }
            if (!Schema::hasColumn('production_order_operations', 'quantity_claimed')) {
                $table->decimal('quantity_claimed', 12, 4)->default(0.0000)->after('is_intermediate');
            }
            if (!Schema::hasColumn('production_order_operations', 'quantity_consumed')) {
                $table->decimal('quantity_consumed', 12, 4)->default(0.0000)->after('quantity_claimed');
            }

            $table->index(['tenant_id', 'production_order_id', 'is_intermediate'], 'idx_po_ops_multi_level');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->dropIndex('idx_po_ops_multi_level');
            $table->dropForeign(['source_product_id']);
            $table->dropForeign(['source_bom_id']);
            $table->dropForeign(['source_routing_id']);
            $table->dropColumn([
                'source_product_id',
                'source_bom_id',
                'source_routing_id',
                'bom_level',
                'target_produced_qty',
                'is_intermediate',
                'quantity_claimed',
                'quantity_consumed',
            ]);
        });
    }
};
