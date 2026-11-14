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
        // 1. Dispatch Orders freight amount
        if (Schema::hasTable('dispatch_orders') && Schema::hasColumn('dispatch_orders', 'freight_amount')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                $table->decimal('freight_amount', 15, 4)->default(0.0000)->change();
            });
        }

        // 2. Transporters opening balance
        if (Schema::hasTable('transporters') && Schema::hasColumn('transporters', 'opening_balance')) {
            Schema::table('transporters', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 4)->default(0.0000)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dispatch_orders') && Schema::hasColumn('dispatch_orders', 'freight_amount')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                $table->decimal('freight_amount', 12, 2)->default(0.00)->change();
            });
        }

        if (Schema::hasTable('transporters') && Schema::hasColumn('transporters', 'opening_balance')) {
            Schema::table('transporters', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 2)->default(0.00)->change();
            });
        }
    }
};
