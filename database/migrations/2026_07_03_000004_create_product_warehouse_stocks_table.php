<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->decimal('unit_cost', 12, 4)->default(0.0000); // Unit cost at opening or current valuation
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id'], 'prod_wh_stock_idx');
            $table->unique(['tenant_id', 'product_id', 'warehouse_id'], 'prod_wh_stock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse_stocks');
    }
};
