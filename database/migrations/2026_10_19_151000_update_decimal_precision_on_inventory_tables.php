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
        // 1. Goods Receipt Note Items (GRN inventory receiving)
        if (Schema::hasTable('goods_receipt_note_items')) {
            Schema::table('goods_receipt_note_items', function (Blueprint $table) {
                if (Schema::hasColumn('goods_receipt_note_items', 'unit_rate')) {
                    $table->decimal('unit_rate', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('goods_receipt_note_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 2. Products pricing and unit costs
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'selling_price')) {
                    $table->decimal('selling_price', 15, 4)->default(0.0000)->nullable()->change();
                }
                if (Schema::hasColumn('products', 'cost_price')) {
                    $table->decimal('cost_price', 15, 4)->default(0.0000)->nullable()->change();
                }
                if (Schema::hasColumn('products', 'opening_stock_rate')) {
                    $table->decimal('opening_stock_rate', 15, 4)->default(0.0000)->nullable()->change();
                }
                if (Schema::hasColumn('products', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->nullable()->change();
                }
            });
        }

        // 3. Product Warehouse Stocks valuation
        if (Schema::hasTable('product_warehouse_stocks')) {
            Schema::table('product_warehouse_stocks', function (Blueprint $table) {
                if (Schema::hasColumn('product_warehouse_stocks', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 4. Stock Transactions valuation
        if (Schema::hasTable('stock_transactions')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('stock_transactions', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('stock_transactions', 'total_value')) {
                    $table->decimal('total_value', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 5. Serial Numbers purchase rate
        if (Schema::hasTable('serial_numbers') && Schema::hasColumn('serial_numbers', 'purchase_rate')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                $table->decimal('purchase_rate', 15, 4)->default(0.0000)->change();
            });
        }

        // 6. Inventory Remnants cost valuation
        if (Schema::hasTable('inventory_remnants') && Schema::hasColumn('inventory_remnants', 'unit_cost')) {
            Schema::table('inventory_remnants', function (Blueprint $table) {
                $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
            });
        }

        // 7. Inventory Remnant Consumptions cost
        if (Schema::hasTable('inventory_remnant_consumptions')) {
            Schema::table('inventory_remnant_consumptions', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_remnant_consumptions', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('inventory_remnant_consumptions', 'total_cost')) {
                    $table->decimal('total_cost', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 8. Production Cost Adjustments
        if (Schema::hasTable('production_cost_adjustments') && Schema::hasColumn('production_cost_adjustments', 'amount')) {
            Schema::table('production_cost_adjustments', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }

        // 9. Maintenance Work Order Spares costs
        if (Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::table('production_maintenance_work_order_spares', function (Blueprint $table) {
                if (Schema::hasColumn('production_maintenance_work_order_spares', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('production_maintenance_work_order_spares', 'total_cost')) {
                    $table->decimal('total_cost', 15, 4)->default(0.0000)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Goods Receipt Note Items
        if (Schema::hasTable('goods_receipt_note_items')) {
            Schema::table('goods_receipt_note_items', function (Blueprint $table) {
                if (Schema::hasColumn('goods_receipt_note_items', 'unit_rate')) {
                    $table->decimal('unit_rate', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('goods_receipt_note_items', 'total_amount')) {
                    $table->decimal('total_amount', 12, 2)->default(0.00)->change();
                }
            });
        }

        // 2. Products pricing and unit costs
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'selling_price')) {
                    $table->decimal('selling_price', 12, 4)->default(0.0000)->nullable()->change();
                }
                if (Schema::hasColumn('products', 'cost_price')) {
                    $table->decimal('cost_price', 12, 4)->default(0.0000)->nullable()->change();
                }
                if (Schema::hasColumn('products', 'opening_stock_rate')) {
                    $table->decimal('opening_stock_rate', 12, 4)->default(0.0000)->nullable()->change();
                }
                if (Schema::hasColumn('products', 'unit_cost')) {
                    $table->decimal('unit_cost', 12, 4)->default(0.0000)->nullable()->change();
                }
            });
        }

        // 3. Product Warehouse Stocks valuation
        if (Schema::hasTable('product_warehouse_stocks')) {
            Schema::table('product_warehouse_stocks', function (Blueprint $table) {
                if (Schema::hasColumn('product_warehouse_stocks', 'unit_cost')) {
                    $table->decimal('unit_cost', 12, 4)->default(0.0000)->change();
                }
            });
        }

        // 4. Stock Transactions valuation
        if (Schema::hasTable('stock_transactions')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('stock_transactions', 'unit_cost')) {
                    $table->decimal('unit_cost', 12, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('stock_transactions', 'total_value')) {
                    $table->decimal('total_value', 12, 4)->default(0.0000)->change();
                }
            });
        }

        // 5. Serial Numbers purchase rate
        if (Schema::hasTable('serial_numbers') && Schema::hasColumn('serial_numbers', 'purchase_rate')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                $table->decimal('purchase_rate', 12, 4)->default(0.0000)->change();
            });
        }

        // 6. Inventory Remnants cost valuation
        if (Schema::hasTable('inventory_remnants') && Schema::hasColumn('inventory_remnants', 'unit_cost')) {
            Schema::table('inventory_remnants', function (Blueprint $table) {
                $table->decimal('unit_cost', 14, 4)->default(0.0000)->change();
            });
        }

        // 7. Inventory Remnant Consumptions cost
        if (Schema::hasTable('inventory_remnant_consumptions')) {
            Schema::table('inventory_remnant_consumptions', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_remnant_consumptions', 'unit_cost')) {
                    $table->decimal('unit_cost', 14, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('inventory_remnant_consumptions', 'total_cost')) {
                    $table->decimal('total_cost', 14, 4)->default(0.0000)->change();
                }
            });
        }

        // 8. Production Cost Adjustments
        if (Schema::hasTable('production_cost_adjustments') && Schema::hasColumn('production_cost_adjustments', 'amount')) {
            Schema::table('production_cost_adjustments', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->change();
            });
        }

        // 9. Maintenance Work Order Spares costs
        if (Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::table('production_maintenance_work_order_spares', function (Blueprint $table) {
                if (Schema::hasColumn('production_maintenance_work_order_spares', 'unit_cost')) {
                    $table->decimal('unit_cost', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('production_maintenance_work_order_spares', 'total_cost')) {
                    $table->decimal('total_cost', 12, 2)->default(0.00)->change();
                }
            });
        }
    }
};
