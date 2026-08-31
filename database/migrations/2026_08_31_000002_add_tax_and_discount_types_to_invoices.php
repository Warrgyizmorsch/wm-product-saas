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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'discount_type')) {
                $table->string('discount_type')->default('item_wise')->after('tax_amount');
            }
            if (!Schema::hasColumn('invoices', 'tax_type')) {
                $table->string('tax_type')->default('item_wise_tax')->after('discount_type');
            }
            if (!Schema::hasColumn('invoices', 'order_tax_rate')) {
                $table->decimal('order_tax_rate', 5, 2)->default(0)->after('tax_type');
            }
            if (!Schema::hasColumn('invoices', 'adjustment')) {
                $table->decimal('adjustment', 12, 2)->default(0)->after('freight_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'tax_type', 'order_tax_rate', 'adjustment']);
        });
    }
};
