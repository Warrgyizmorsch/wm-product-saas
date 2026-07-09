<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('delivery_order_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('return_number')->index();
            $table->date('return_date');
            $table->string('status')->default('Draft')->index(); // Draft, Approved, Completed, Cancelled
            $table->decimal('total_refund_amount', 15, 2)->default(0.00);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id']);
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->unsignedBigInteger('delivery_order_item_id')->nullable();
            $table->unsignedBigInteger('invoice_item_id')->nullable();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
