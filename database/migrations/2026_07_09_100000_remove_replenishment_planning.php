<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop sales_order_allocations table
        Schema::dropIfExists('sales_order_allocations');

        // 2. Drop planning_status from sales_orders (SQLite safe: drop index first)
        if (Schema::hasTable('sales_orders') && Schema::hasColumn('sales_orders', 'planning_status')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropIndex(['planning_status']);
                $table->dropColumn('planning_status');
            });
        }

        // 3. Drop fulfillment_method from sales_order_items
        if (Schema::hasTable('sales_order_items') && Schema::hasColumn('sales_order_items', 'fulfillment_method')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropColumn('fulfillment_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_order_items') && !Schema::hasColumn('sales_order_items', 'fulfillment_method')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->string('fulfillment_method')->default('Auto')->after('amount');
            });
        }

        if (Schema::hasTable('sales_orders') && !Schema::hasColumn('sales_orders', 'planning_status')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('planning_status')->default('Pending')->after('status')->index();
            });
        }

        if (!Schema::hasTable('sales_order_allocations')) {
            Schema::create('sales_order_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('sales_order_id')->index();
                $table->unsignedBigInteger('sales_order_item_id')->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->decimal('reserved_qty', 12, 4);
                $table->timestamps();
            });
        }
    }
};
