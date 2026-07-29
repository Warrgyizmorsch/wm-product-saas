<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_returns', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('sales_returns', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0.00)->after('status');
            }
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_return_items', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('sales_return_items', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0.00)->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sales_returns', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('sales_returns', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_return_items', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
            if (Schema::hasColumn('sales_return_items', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
        });
    }
};
