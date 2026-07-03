<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('bom_item_id')->nullable()
                ->constrained('production_bom_items')
                ->nullOnDelete();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            $table->decimal('quantity_planned', 12, 4);
            $table->decimal('quantity_reserved', 12, 4)->default(0.0000);
            $table->decimal('quantity_issued', 12, 4)->default(0.0000);

            $table->foreignId('uom_id')->constrained('uoms');

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_res_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_reservations');
    }
};
