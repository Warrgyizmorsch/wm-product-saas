<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'discount_type')) {
                $table->string('discount_type')->nullable()->default('item_wise');
            }
            if (!Schema::hasColumn('sales_orders', 'tax_type')) {
                $table->string('tax_type')->nullable()->default('item_wise_tax');
            }
            if (!Schema::hasColumn('sales_orders', 'gst_type')) {
                $table->string('gst_type')->nullable()->default('cgst_sgst');
            }
            if (!Schema::hasColumn('sales_orders', 'order_tax_rate')) {
                $table->decimal('order_tax_rate', 5, 2)->nullable()->default(0.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $cols = ['discount_type', 'tax_type', 'gst_type', 'order_tax_rate'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('sales_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
