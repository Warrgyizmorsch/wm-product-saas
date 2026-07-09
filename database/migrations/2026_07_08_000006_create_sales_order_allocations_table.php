<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add planning_status to sales_orders
        if (Schema::hasTable('sales_orders') && !Schema::hasColumn('sales_orders', 'planning_status')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('planning_status')->default('Pending')->after('status')->index(); // Pending, Completed
            });
        }

        // 2. Create sales_order_allocations table
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

    public function down(): void
    {
        Schema::dropIfExists('sales_order_allocations');

        if (Schema::hasTable('sales_orders') && Schema::hasColumn('sales_orders', 'planning_status')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('planning_status');
            });
        }
    }
};
