<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dispatch_orders')) {
            Schema::create('dispatch_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('delivery_order_id')->index();
                $table->unsignedBigInteger('sales_order_id')->index();
                $table->string('dispatch_number', 50)->unique();
                $table->date('dispatch_date');
                $table->string('carrier', 255)->nullable();
                $table->string('tracking_number', 255)->nullable();
                $table->string('vehicle_number', 100)->nullable();
                $table->string('driver_name', 150)->nullable();
                $table->string('driver_phone', 20)->nullable();
                $table->string('status', 50)->default('Pending');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('delivery_order_id')->references('id')->on('delivery_orders')->cascadeOnDelete();
                $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('dispatch_order_items')) {
            Schema::create('dispatch_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dispatch_order_id')->index();
                $table->unsignedBigInteger('delivery_order_item_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('quantity_ordered', 12, 4)->default(0);
                $table->decimal('quantity_dispatched', 12, 4)->default(0);
                $table->timestamps();

                $table->foreign('dispatch_order_id')->references('id')->on('dispatch_orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_order_items');
        Schema::dropIfExists('dispatch_orders');
    }
};
