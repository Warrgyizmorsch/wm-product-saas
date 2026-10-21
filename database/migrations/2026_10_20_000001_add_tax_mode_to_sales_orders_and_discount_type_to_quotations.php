<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales_orders') && !Schema::hasColumn('sales_orders', 'tax_mode')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('tax_mode')->default('exclusive')->after('gst_type');
            });
        }

        if (Schema::hasTable('quotations') && !Schema::hasColumn('quotations', 'discount_type')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->string('discount_type')->default('order_wise')->after('tax_type');
            });
        }

        if (Schema::hasTable('quotation_items') && !Schema::hasColumn('quotation_items', 'discount')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                $table->decimal('discount', 15, 2)->default(0.00)->after('tax_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_orders') && Schema::hasColumn('sales_orders', 'tax_mode')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('tax_mode');
            });
        }

        if (Schema::hasTable('quotations') && Schema::hasColumn('quotations', 'discount_type')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('discount_type');
            });
        }

        if (Schema::hasTable('quotation_items') && Schema::hasColumn('quotation_items', 'discount')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                $table->dropColumn('discount');
            });
        }
    }
};
