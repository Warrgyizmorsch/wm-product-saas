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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'minimum_order_qty')) {
                    $table->decimal('minimum_order_qty', 12, 4)->default(0.0000)->after('reorder_point');
                }
                if (!Schema::hasColumn('products', 'order_multiple')) {
                    $table->decimal('order_multiple', 12, 4)->default(0.0000)->after('minimum_order_qty');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'order_multiple')) {
                    $table->dropColumn('order_multiple');
                }
                if (Schema::hasColumn('products', 'minimum_order_qty')) {
                    $table->dropColumn('minimum_order_qty');
                }
            });
        }
    }
};
