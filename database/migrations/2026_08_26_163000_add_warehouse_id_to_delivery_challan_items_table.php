<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_challan_items') && !Schema::hasColumn('delivery_challan_items', 'warehouse_id')) {
            Schema::table('delivery_challan_items', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('product_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_challan_items') && Schema::hasColumn('delivery_challan_items', 'warehouse_id')) {
            Schema::table('delivery_challan_items', function (Blueprint $table) {
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
