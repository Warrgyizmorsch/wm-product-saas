<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add planning_type to products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'planning_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('planning_type')->default('stock')->after('type'); // stock, manufacture, purchase, manual
            });
        }

        // 2. Add fulfillment_method to sales_order_items
        if (Schema::hasTable('sales_order_items') && !Schema::hasColumn('sales_order_items', 'fulfillment_method')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->string('fulfillment_method')->default('Auto')->after('amount'); // Auto, Stock, Manufacture, Purchase
            });
        }

        // 3. Create purchase_requisitions and items
        if (!Schema::hasTable('purchase_requisitions')) {
            Schema::create('purchase_requisitions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('requisition_number')->unique();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->date('requisition_date');
                $table->string('status')->default('Draft'); // Draft, Approved, Cancelled
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('sales_order_id')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('sales_order_id');
            });
        }

        if (!Schema::hasTable('purchase_requisition_items')) {
            Schema::create('purchase_requisition_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_requisition_id');
                $table->unsignedBigInteger('sales_order_item_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('quantity', 12, 4);
                $table->decimal('estimated_cost', 12, 2)->default(0.00);
                $table->timestamps();

                $table->index('purchase_requisition_id');
                $table->index('sales_order_item_id');
            });
        }

        // 4. Add sales_order_id & sales_order_item_id to production_orders
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('production_orders', 'sales_order_id')) {
                    $table->unsignedBigInteger('sales_order_id')->nullable()->after('bom_id');
                }
                if (!Schema::hasColumn('production_orders', 'sales_order_item_id')) {
                    $table->unsignedBigInteger('sales_order_item_id')->nullable()->after('sales_order_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'planning_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('planning_type');
            });
        }

        if (Schema::hasTable('sales_order_items') && Schema::hasColumn('sales_order_items', 'fulfillment_method')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropColumn('fulfillment_method');
            });
        }

        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');

        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                if (Schema::hasColumn('production_orders', 'sales_order_id')) {
                    $table->dropColumn('sales_order_id');
                }
                if (Schema::hasColumn('production_orders', 'sales_order_item_id')) {
                    $table->dropColumn('sales_order_item_id');
                }
            });
        }
    }
};
