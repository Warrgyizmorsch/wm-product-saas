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
        $isNotSqlite = config('database.default') !== 'sqlite';

        // 1. Drop foreign keys referencing delivery_orders or delivery_order_items
        Schema::table('dispatch_orders', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('dispatch_orders_delivery_order_id_foreign');
            }
        });

        Schema::table('serial_numbers', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('serial_numbers_delivery_order_item_id_foreign');
            }
        });

        Schema::table('production_order_requests', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('production_order_requests_delivery_order_item_id_foreign');
            }
        });

        Schema::table('delivery_order_items', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('delivery_order_items_delivery_order_id_foreign');
            }
        });

        // 2. Rename tables
        Schema::rename('delivery_orders', 'material_requirements');
        Schema::rename('delivery_order_items', 'material_requirement_items');

        // 3. Rename columns in the renamed tables
        Schema::table('material_requirements', function (Blueprint $table) {
            $table->renameColumn('delivery_number', 'requirement_number');
            $table->renameColumn('delivery_date', 'requirement_date');
        });

        Schema::table('material_requirement_items', function (Blueprint $table) {
            $table->renameColumn('delivery_order_id', 'material_requirement_id');
        });

        // 4. Rename columns in other referencing tables
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('delivery_order_id', 'material_requirement_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('delivery_order_item_id', 'material_requirement_item_id');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->renameColumn('delivery_order_id', 'material_requirement_id');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->renameColumn('delivery_order_item_id', 'material_requirement_item_id');
        });

        Schema::table('dispatch_orders', function (Blueprint $table) {
            $table->renameColumn('delivery_order_id', 'material_requirement_id');
        });

        Schema::table('dispatch_order_items', function (Blueprint $table) {
            $table->renameColumn('delivery_order_item_id', 'material_requirement_item_id');
        });

        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->renameColumn('delivery_order_item_id', 'material_requirement_item_id');
        });

        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->renameColumn('delivery_order_item_id', 'material_requirement_item_id');
        });

        // 5. Recreate foreign keys with correct column and table names
        Schema::table('material_requirement_items', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('material_requirement_id', 'mr_items_mr_id_foreign')
                    ->references('id')
                    ->on('material_requirements')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('dispatch_orders', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('material_requirement_id', 'dispatch_orders_mr_id_foreign')
                    ->references('id')
                    ->on('material_requirements')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('serial_numbers', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('material_requirement_item_id', 'serial_numbers_mr_item_id_foreign')
                    ->references('id')
                    ->on('material_requirement_items')
                    ->nullOnDelete();
            }
        });

        Schema::table('production_order_requests', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('material_requirement_item_id', 'prod_order_requests_mr_item_id_foreign')
                    ->references('id')
                    ->on('material_requirement_items')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isNotSqlite = config('database.default') !== 'sqlite';

        // 1. Drop the recreated foreign keys
        Schema::table('production_order_requests', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('prod_order_requests_mr_item_id_foreign');
            }
        });

        Schema::table('serial_numbers', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('serial_numbers_mr_item_id_foreign');
            }
        });

        Schema::table('dispatch_orders', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('dispatch_orders_mr_id_foreign');
            }
        });

        Schema::table('material_requirement_items', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->dropForeign('mr_items_mr_id_foreign');
            }
        });

        // 2. Rename columns in other referencing tables back
        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->renameColumn('material_requirement_item_id', 'delivery_order_item_id');
        });

        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->renameColumn('material_requirement_item_id', 'delivery_order_item_id');
        });

        Schema::table('dispatch_order_items', function (Blueprint $table) {
            $table->renameColumn('material_requirement_item_id', 'delivery_order_item_id');
        });

        Schema::table('dispatch_orders', function (Blueprint $table) {
            $table->renameColumn('material_requirement_id', 'delivery_order_id');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->renameColumn('material_requirement_item_id', 'delivery_order_item_id');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->renameColumn('material_requirement_id', 'delivery_order_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('material_requirement_item_id', 'delivery_order_item_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('material_requirement_id', 'delivery_order_id');
        });

        // 3. Rename columns in tables back
        Schema::table('material_requirement_items', function (Blueprint $table) {
            $table->renameColumn('material_requirement_id', 'delivery_order_id');
        });

        Schema::table('material_requirements', function (Blueprint $table) {
            $table->renameColumn('requirement_date', 'delivery_date');
            $table->renameColumn('requirement_number', 'delivery_number');
        });

        // 4. Rename tables back
        Schema::rename('material_requirements', 'delivery_orders');
        Schema::rename('material_requirement_items', 'delivery_order_items');

        // 5. Recreate original foreign keys
        Schema::table('delivery_order_items', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('delivery_order_id')
                    ->references('id')
                    ->on('delivery_orders')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('production_order_requests', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('delivery_order_item_id')
                    ->references('id')
                    ->on('delivery_order_items')
                    ->onDelete('set null');
            }
        });

        Schema::table('serial_numbers', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('delivery_order_item_id')
                    ->references('id')
                    ->on('delivery_order_items')
                    ->nullOnDelete();
            }
        });

        Schema::table('dispatch_orders', function (Blueprint $table) use ($isNotSqlite) {
            if ($isNotSqlite) {
                $table->foreign('delivery_order_id')
                    ->references('id')
                    ->on('delivery_orders')
                    ->cascadeOnDelete();
            }
        });
    }
};
