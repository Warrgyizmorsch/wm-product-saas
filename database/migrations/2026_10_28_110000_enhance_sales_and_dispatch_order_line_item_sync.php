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
        // 1. Add tracking columns to sales_order_items
        if (Schema::hasTable('sales_order_items')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_items', 'allocated_qty')) {
                    $table->decimal('allocated_qty', 15, 4)->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('sales_order_items', 'dispatched_qty')) {
                    $table->decimal('dispatched_qty', 15, 4)->default(0)->after('allocated_qty');
                }
                if (!Schema::hasColumn('sales_order_items', 'invoiced_qty')) {
                    $table->decimal('invoiced_qty', 15, 4)->default(0)->after('dispatched_qty');
                }
                if (!Schema::hasColumn('sales_order_items', 'cancelled_qty')) {
                    $table->decimal('cancelled_qty', 15, 4)->default(0)->after('invoiced_qty');
                }
                if (!Schema::hasColumn('sales_order_items', 'status')) {
                    $table->string('status', 50)->default('Pending')->after('cancelled_qty');
                }
            });
        }

        // 2. Add sales_order_item_id foreign key to dispatch_order_items
        if (Schema::hasTable('dispatch_order_items')) {
            Schema::table('dispatch_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('dispatch_order_items', 'sales_order_item_id')) {
                    $table->foreignId('sales_order_item_id')
                        ->nullable()
                        ->after('dispatch_order_id')
                        ->constrained('sales_order_items')
                        ->nullOnDelete();
                }
            });
        }

        // 3. Add short_close_reason / cancel_reason to sales_orders if missing
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_orders', 'closure_reason')) {
                    $table->string('closure_reason', 500)->nullable()->after('notes');
                }
            });
        }

        // 4. Add cancellation_reason to dispatch_orders if missing
        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('dispatch_orders', 'cancellation_reason')) {
                    $table->string('cancellation_reason', 500)->nullable()->after('notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dispatch_order_items')) {
            Schema::table('dispatch_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('dispatch_order_items', 'sales_order_item_id')) {
                    $table->dropForeign(['sales_order_item_id']);
                    $table->dropColumn('sales_order_item_id');
                }
            });
        }

        if (Schema::hasTable('sales_order_items')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $cols = ['allocated_qty', 'dispatched_qty', 'invoiced_qty', 'cancelled_qty', 'status'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('sales_order_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('sales_orders') && Schema::hasColumn('sales_orders', 'closure_reason')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('closure_reason');
            });
        }

        if (Schema::hasTable('dispatch_orders') && Schema::hasColumn('dispatch_orders', 'cancellation_reason')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                $table->dropColumn('cancellation_reason');
            });
        }
    }
};
