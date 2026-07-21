<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('goods_receipt_notes')) {
            Schema::create('goods_receipt_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('grn_number')->unique();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->date('received_date');
                $table->string('challan_number')->nullable();
                $table->date('challan_date')->nullable();
                $table->string('vehicle_number')->nullable();
                $table->string('transporter_name')->nullable();
                $table->string('lr_number')->nullable();
                $table->string('status')->default('Draft'); // Draft, Approved, Cancelled
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('goods_receipt_note_items')) {
            Schema::create('goods_receipt_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('goods_receipt_note_id')->index();
                $table->unsignedBigInteger('purchase_order_item_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('ordered_qty', 12, 4)->default(0.0000);
                $table->decimal('previous_received_qty', 12, 4)->default(0.0000);
                $table->decimal('received_qty', 12, 4)->default(0.0000);
                $table->decimal('accepted_qty', 12, 4)->default(0.0000);
                $table->decimal('rejected_qty', 12, 4)->default(0.0000);
                $table->decimal('remaining_qty', 12, 4)->default(0.0000);
                $table->decimal('unit_rate', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('goods_receipt_note_id')
                    ->references('id')
                    ->on('goods_receipt_notes')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('purchase_order_items') && !Schema::hasColumn('purchase_order_items', 'received_qty')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('received_qty', 12, 4)->default(0.0000)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_order_items') && Schema::hasColumn('purchase_order_items', 'received_qty')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('received_qty');
            });
        }

        Schema::dropIfExists('goods_receipt_note_items');
        Schema::dropIfExists('goods_receipt_notes');
    }
};
