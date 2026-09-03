<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('dispatch_orders', 'invoice_id')) {
                    $table->unsignedBigInteger('invoice_id')->nullable()->after('sales_order_id')->index();
                    $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('dispatch_order_items')) {
            Schema::table('dispatch_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('dispatch_order_items', 'invoice_item_id')) {
                    $table->unsignedBigInteger('invoice_item_id')->nullable()->after('material_requirement_item_id')->index();
                    $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispatch_order_items')) {
            Schema::table('dispatch_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('dispatch_order_items', 'invoice_item_id')) {
                    $table->dropForeign(['invoice_item_id']);
                    $table->dropColumn('invoice_item_id');
                }
            });
        }

        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                if (Schema::hasColumn('dispatch_orders', 'invoice_id')) {
                    $table->dropForeign(['invoice_id']);
                    $table->dropColumn('invoice_id');
                }
            });
        }
    }
};
