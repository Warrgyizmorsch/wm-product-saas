<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add warehouse_id to sales_order_items
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
        });

        // 2. Add reference_item_id and expires_at to stock_reservations
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_item_id')->nullable()->after('reference_id');
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->index(['reference_type', 'reference_id', 'reference_item_id'], 'res_document_line_index');
        });

        // 3. Create delivery_orders table
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('delivery_number')->index();
            $table->date('delivery_date');
            $table->string('status')->default('Draft')->index(); // Draft, Shipped, Cancelled
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'sales_order_id']);
        });

        // 4. Create delivery_order_items table
        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->timestamps();
        });

        // 5. Add delivery_order_item_id to serial_numbers to track exact serials sent in a shipment
        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->foreignId('delivery_order_item_id')->nullable()->after('stock_transaction_id_out')->constrained('delivery_order_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serial_numbers', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['delivery_order_item_id']);
            }
            $table->dropColumn('delivery_order_item_id');
        });

        Schema::dropIfExists('delivery_order_items');
        Schema::dropIfExists('delivery_orders');

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex('res_document_line_index');
            $table->dropColumn(['reference_item_id', 'expires_at']);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['warehouse_id']);
            }
            $table->dropColumn('warehouse_id');
        });
    }
};
