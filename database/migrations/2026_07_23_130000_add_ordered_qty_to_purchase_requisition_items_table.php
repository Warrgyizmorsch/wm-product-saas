<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_requisition_items') && !Schema::hasColumn('purchase_requisition_items', 'ordered_qty')) {
            Schema::table('purchase_requisition_items', function (Blueprint $table) {
                $table->decimal('ordered_qty', 12, 4)->default(0.0000)->after('quantity');
            });
        }

        if (Schema::hasTable('purchase_order_items') && !Schema::hasColumn('purchase_order_items', 'requisition_item_allocations')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->json('requisition_item_allocations')->nullable()->after('product_id');
            });
        }

        // Data migration: Populate existing ordered quantities on purchase_requisition_items & PO item allocations
        if (Schema::hasTable('purchase_order_items') && Schema::hasTable('purchase_requisition_items')) {
            $poItems = DB::table('purchase_order_items')
                ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                ->whereNotNull('purchase_orders.purchase_requisition_id')
                ->where('purchase_orders.status', '!=', 'Cancelled')
                ->select(
                    'purchase_order_items.id',
                    'purchase_order_items.product_id',
                    'purchase_order_items.quantity',
                    'purchase_orders.purchase_requisition_id'
                )
                ->get();

            foreach ($poItems as $poItem) {
                // Find matching PR item
                $prItem = DB::table('purchase_requisition_items')
                    ->where('purchase_requisition_id', $poItem->purchase_requisition_id)
                    ->where('product_id', $poItem->product_id)
                    ->first();

                if ($prItem) {
                    // Update ordered_qty on PR item
                    $newOrdered = (float)$prItem->ordered_qty + (float)$poItem->quantity;
                    DB::table('purchase_requisition_items')
                        ->where('id', $prItem->id)
                        ->update(['ordered_qty' => $newOrdered]);

                    // Add allocation JSON to PO item
                    $allocations = [
                        [
                            'pr_item_id' => $prItem->id,
                            'quantity' => (float)$poItem->quantity
                        ]
                    ];
                    DB::table('purchase_order_items')
                        ->where('id', $poItem->id)
                        ->update(['requisition_item_allocations' => json_encode($allocations)]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_requisition_items') && Schema::hasColumn('purchase_requisition_items', 'ordered_qty')) {
            Schema::table('purchase_requisition_items', function (Blueprint $table) {
                $table->dropColumn('ordered_qty');
            });
        }

        if (Schema::hasTable('purchase_order_items') && Schema::hasColumn('purchase_order_items', 'requisition_item_allocations')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('requisition_item_allocations');
            });
        }
    }
};
