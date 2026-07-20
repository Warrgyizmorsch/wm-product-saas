<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('purchase_order_number')->unique();
                $table->unsignedBigInteger('purchase_requisition_id')->nullable();
                $table->unsignedBigInteger('vendor_id');
                $table->string('location')->nullable();
                $table->text('billing_address')->nullable();
                $table->string('reference')->nullable();
                $table->date('date');
                $table->date('delivery_date')->nullable();
                $table->string('discount_type')->default('without_discount'); // without_discount, item_wise, order_wise
                $table->string('tax_type')->default('without_tax'); // without_tax, item_wise_tax, order_wise_tax
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('cgst_amount', 12, 2)->default(0.00);
                $table->decimal('sgst_amount', 12, 2)->default(0.00);
                $table->decimal('igst_amount', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->string('status')->default('Draft'); // Draft, Approved, Cancelled
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('purchase_requisition_id');
                $table->index('vendor_id');
            });
        }

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('quantity', 12, 4);
                $table->decimal('rate', 12, 2);
                $table->decimal('amount', 12, 2);
                $table->decimal('discount_percent', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('tax_percent', 5, 2)->default(0.00);
                $table->decimal('cgst_percent', 5, 2)->default(0.00);
                $table->decimal('sgst_percent', 5, 2)->default(0.00);
                $table->decimal('igst_percent', 5, 2)->default(0.00);
                $table->decimal('cgst_amount', 12, 2)->default(0.00);
                $table->decimal('sgst_amount', 12, 2)->default(0.00);
                $table->decimal('igst_amount', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('purchase_order_id')
                    ->references('id')
                    ->on('purchase_orders')
                    ->onDelete('cascade');

                $table->index('product_id');
                $table->index('warehouse_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
