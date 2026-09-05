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
        Schema::table('vendor_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_bills', 'dispatch_order_id')) {
                $table->foreignId('dispatch_order_id')->nullable()->after('goods_receipt_note_id')->constrained('dispatch_orders')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_bills', 'dispatch_order_id')) {
                $table->dropForeign(['dispatch_order_id']);
                $table->dropColumn('dispatch_order_id');
            }
        });
    }
};
