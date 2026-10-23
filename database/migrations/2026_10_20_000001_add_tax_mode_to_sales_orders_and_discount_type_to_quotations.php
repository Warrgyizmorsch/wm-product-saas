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

        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if (!Schema::hasColumn('quotations', 'tax_mode')) {
                    $table->string('tax_mode')->default('exclusive')->after('status');
                }
                if (!Schema::hasColumn('quotations', 'tax_type')) {
                    $table->string('tax_type')->default('intra_state')->after('tax_mode');
                }
                if (!Schema::hasColumn('quotations', 'discount_type')) {
                    $table->string('discount_type')->default('order_wise')->after('tax_type');
                }
                if (!Schema::hasColumn('quotations', 'cgst')) {
                    $table->decimal('cgst', 15, 2)->default(0.00)->after('tax');
                }
                if (!Schema::hasColumn('quotations', 'sgst')) {
                    $table->decimal('sgst', 15, 2)->default(0.00)->after('cgst');
                }
                if (!Schema::hasColumn('quotations', 'igst')) {
                    $table->decimal('igst', 15, 2)->default(0.00)->after('sgst');
                }
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

        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                $colsToDrop = array_filter(
                    ['tax_mode', 'tax_type', 'discount_type', 'cgst', 'sgst', 'igst'],
                    fn ($col) => Schema::hasColumn('quotations', $col)
                );
                if (!empty($colsToDrop)) {
                    $table->dropColumn($colsToDrop);
                }
            });
        }

        if (Schema::hasTable('quotation_items') && Schema::hasColumn('quotation_items', 'discount')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                $table->dropColumn('discount');
            });
        }
    }
};
