<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add valuation columns to products
        Schema::table('products', function (Blueprint $table) {
            $table->string('inventory_valuation_method')->default('FIFO')->after('track_batch');
        });

        // Add reservation and availability columns to product_warehouse_stocks
        Schema::table('product_warehouse_stocks', function (Blueprint $table) {
            $table->decimal('reserved_qty', 12, 4)->default(0.0000)->after('quantity');
            $table->decimal('available_qty', 12, 4)->default(0.0000)->after('reserved_qty');
        });

        // Recreate batches table (with warehouse_id, quantity, and available_qty)
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('batch_number');
            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->decimal('available_qty', 12, 4)->default(0.0000);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->unique(['tenant_id', 'product_id', 'warehouse_id', 'batch_number'], 'product_batch_warehouse_unique');
        });

        // Recreate stock_transactions table (with reference_type and reference_id)
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('type'); // IN, OUT
            $table->string('reference_type'); // e.g. Opening Stock, GRN, Invoice, Stock Adjustment, Transfer, Manufacturing
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->decimal('unit_cost', 12, 4)->default(0.0000);
            $table->decimal('total_value', 12, 4)->default(0.0000);
            $table->decimal('balance_qty', 12, 4)->default(0.0000);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Recreate serial_numbers table (with purchase_rate and standard status list)
        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('serial_number');
            $table->string('status')->default('Available'); // Available, Reserved, Sold, Returned, Damaged, In Transit, Scrapped
            $table->decimal('purchase_rate', 12, 4)->default(0.0000);
            $table->foreignId('stock_transaction_id_in')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->foreignId('stock_transaction_id_out')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->unique(['tenant_id', 'product_id', 'serial_number'], 'product_serial_tenant_unique');
        });

        // Create stock_reservations table
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('reference_type'); // Sales Order, Transfer, Manufacturing
            $table->unsignedBigInteger('reference_id');
            $table->decimal('reserved_qty', 12, 4)->default(0.0000);
            $table->string('status')->default('Active'); // Active, Completed, Cancelled
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('batches');
        Schema::table('product_warehouse_stocks', function (Blueprint $table) {
            $table->dropColumn(['reserved_qty', 'available_qty']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('inventory_valuation_method');
        });
    }
};
