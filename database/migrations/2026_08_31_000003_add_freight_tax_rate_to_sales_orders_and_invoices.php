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
            if (!Schema::hasColumn('sales_orders', 'freight_tax_rate')) {
                $table->string('freight_tax_rate')->default('highest')->after('freight_amount');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'freight_tax_rate')) {
                $table->string('freight_tax_rate')->default('highest')->after('freight_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['freight_tax_rate']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['freight_tax_rate']);
        });
    }
};
