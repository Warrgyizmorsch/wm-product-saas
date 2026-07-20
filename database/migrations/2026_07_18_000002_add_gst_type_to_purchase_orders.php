<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && !Schema::hasColumn('purchase_orders', 'gst_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('gst_type')->default('cgst_sgst')->after('tax_type'); // cgst_sgst, igst
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'gst_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('gst_type');
            });
        }
    }
};
