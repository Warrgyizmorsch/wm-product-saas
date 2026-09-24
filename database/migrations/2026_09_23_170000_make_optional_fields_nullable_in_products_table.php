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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('cost_price', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('unit_cost', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('gst_rate', 5, 2)->nullable()->default(18.00)->change();
            $table->decimal('reorder_point', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('minimum_order_qty', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('order_multiple', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('opening_stock', 12, 4)->nullable()->default(0.0000)->change();
            $table->decimal('opening_stock_rate', 12, 4)->nullable()->default(0.0000)->change();
            $table->boolean('track_serial_number')->nullable()->default(false)->change();
            $table->boolean('track_batch')->nullable()->default(false)->change();
            $table->string('inventory_valuation_method', 191)->nullable()->default('FIFO')->change();
            $table->string('planning_type', 191)->nullable()->default('stock')->change();
            $table->string('default_production_model', 191)->nullable()->default('pure_manufacturing')->change();
            $table->string('supplier_method', 191)->nullable()->default('trade')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 4)->default(0.0000)->change();
            $table->decimal('cost_price', 12, 4)->default(0.0000)->change();
            $table->decimal('unit_cost', 12, 4)->default(0.0000)->change();
            $table->decimal('gst_rate', 5, 2)->default(18.00)->change();
            $table->decimal('reorder_point', 12, 4)->default(0.0000)->change();
            $table->decimal('minimum_order_qty', 12, 4)->default(0.0000)->change();
            $table->decimal('order_multiple', 12, 4)->default(0.0000)->change();
            $table->decimal('opening_stock', 12, 4)->default(0.0000)->change();
            $table->decimal('opening_stock_rate', 12, 4)->default(0.0000)->change();
            $table->boolean('track_serial_number')->default(false)->change();
            $table->boolean('track_batch')->default(false)->change();
            $table->string('inventory_valuation_method', 191)->default('FIFO')->change();
            $table->string('planning_type', 191)->default('stock')->change();
            $table->string('default_production_model', 191)->default('pure_manufacturing')->change();
            $table->string('supplier_method', 191)->default('trade')->change();
        });
    }
};
