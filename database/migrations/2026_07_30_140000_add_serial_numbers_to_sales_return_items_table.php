<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_return_items') && !Schema::hasColumn('sales_return_items', 'serial_numbers')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->text('serial_numbers')->nullable()->after('total_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_return_items') && Schema::hasColumn('sales_return_items', 'serial_numbers')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->dropColumn('serial_numbers');
            });
        }
    }
};
