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
        Schema::create('production_order_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('delivery_order_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity_requested', 12, 4);
            $table->string('status')->default('draft'); // draft, approved, rejected, completed
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->timestamps();

            $table->foreign('delivery_order_item_id')
                ->references('id')
                ->on('delivery_order_items')
                ->onDelete('set null');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            $table->foreign('production_order_id')
                ->references('id')
                ->on('production_orders')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_requests');
    }
};
