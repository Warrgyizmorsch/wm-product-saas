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
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'freight_terms')) {
                $table->string('freight_terms', 50)->default('To Pay')->after('shipping_address');
            }
            if (!Schema::hasColumn('sales_orders', 'freight_amount')) {
                $table->decimal('freight_amount', 12, 2)->default(0.00)->after('freight_terms');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'freight_terms')) {
                $table->string('freight_terms', 50)->default('To Pay')->after('notes');
            }
            if (!Schema::hasColumn('invoices', 'freight_amount')) {
                $table->decimal('freight_amount', 12, 2)->default(0.00)->after('freight_terms');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'freight_amount')) {
                $table->dropColumn('freight_amount');
            }
            if (Schema::hasColumn('sales_orders', 'freight_terms')) {
                $table->dropColumn('freight_terms');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'freight_amount')) {
                $table->dropColumn('freight_amount');
            }
            if (Schema::hasColumn('invoices', 'freight_terms')) {
                $table->dropColumn('freight_terms');
            }
        });
    }
};
