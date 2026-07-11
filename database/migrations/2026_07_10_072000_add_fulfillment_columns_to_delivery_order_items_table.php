<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->decimal('quantity_ordered', 12, 4)->default(0.0000)->after('quantity');
            $table->decimal('quantity_reserved', 12, 4)->default(0.0000)->after('quantity_ordered');
            $table->string('status')->default('Pending')->after('quantity_reserved');
            $table->unsignedBigInteger('purchase_requisition_id')->nullable()->after('status');
            $table->unsignedBigInteger('production_order_id')->nullable()->after('purchase_requisition_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_ordered',
                'quantity_reserved',
                'status',
                'purchase_requisition_id',
                'production_order_id'
            ]);
        });
    }
};
