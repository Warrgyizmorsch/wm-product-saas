<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('production_plans', 'sales_order_id')) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->after('routing_id');
                $table->index('sales_order_id');
            }
            if (! Schema::hasColumn('production_plans', 'sales_order_item_id')) {
                $table->unsignedBigInteger('sales_order_item_id')->nullable()->after('sales_order_id');
                $table->index('sales_order_item_id');
            }
        });

        Schema::table('production_order_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_requests', 'production_plan_id')) {
                $table->unsignedBigInteger('production_plan_id')->nullable()->after('created_by');
                $table->index('production_plan_id');
            }
        });

        Schema::table('production_order_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_reservations', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
            }
        });

        Schema::table('production_order_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_issues', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
            }
        });

        Schema::table('production_order_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_receipts', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_order_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_receipts', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });

        Schema::table('production_order_issues', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_issues', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });

        Schema::table('production_order_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_reservations', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });

        Schema::table('production_order_requests', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_requests', 'production_plan_id')) {
                $table->dropIndex(['production_plan_id']);
                $table->dropColumn('production_plan_id');
            }
        });

        Schema::table('production_plans', function (Blueprint $table) {
            if (Schema::hasColumn('production_plans', 'sales_order_item_id')) {
                $table->dropIndex(['sales_order_item_id']);
                $table->dropColumn('sales_order_item_id');
            }
            if (Schema::hasColumn('production_plans', 'sales_order_id')) {
                $table->dropIndex(['sales_order_id']);
                $table->dropColumn('sales_order_id');
            }
        });
    }
};
