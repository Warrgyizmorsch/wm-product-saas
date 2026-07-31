<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatch_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('dispatch_orders', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('tenant_id')->index();
            }
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `dispatch_orders` MODIFY `material_requirement_id` BIGINT UNSIGNED NULL;");
            DB::statement("ALTER TABLE `dispatch_orders` MODIFY `sales_order_id` BIGINT UNSIGNED NULL;");
            DB::statement("ALTER TABLE `dispatch_order_items` MODIFY `material_requirement_item_id` BIGINT UNSIGNED NULL;");
        }

        Schema::table('dispatch_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('dispatch_order_items', 'serial_numbers')) {
                $table->text('serial_numbers')->nullable()->after('quantity_dispatched');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('dispatch_order_items', 'serial_numbers')) {
                $table->dropColumn('serial_numbers');
            }
        });

        Schema::table('dispatch_orders', function (Blueprint $table) {
            if (Schema::hasColumn('dispatch_orders', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
        });
    }
};
