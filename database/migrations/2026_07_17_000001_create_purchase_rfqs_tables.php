<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_rfqs')) {
            Schema::create('purchase_rfqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('rfq_number')->unique();
                $table->unsignedBigInteger('purchase_requisition_id')->nullable();
                $table->date('rfq_date');
                $table->string('status')->default('Draft'); // Draft, Sent, Received, Confirmed, Cancelled
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('purchase_requisition_id');
            });
        }

        if (!Schema::hasTable('purchase_rfq_items')) {
            Schema::create('purchase_rfq_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('purchase_rfq_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('quantity', 12, 4);
                $table->decimal('estimated_cost', 12, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('purchase_rfq_id')
                    ->references('id')
                    ->on('purchase_rfqs')
                    ->onDelete('cascade');
                
                $table->index('product_id');
                $table->index('warehouse_id');
            });
        }

        if (!Schema::hasTable('purchase_rfq_vendors')) {
            Schema::create('purchase_rfq_vendors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('purchase_rfq_id');
                $table->unsignedBigInteger('vendor_id');
                $table->string('token')->unique();
                $table->date('delivery_date')->nullable();
                $table->date('validity_date')->nullable();
                $table->string('payment_type')->nullable();
                $table->string('quotation_number')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('status')->default('Sent'); // Sent, Received
                $table->timestamps();

                $table->foreign('purchase_rfq_id')
                    ->references('id')
                    ->on('purchase_rfqs')
                    ->onDelete('cascade');
                
                $table->index('tenant_id');
                $table->index('vendor_id');
            });
        }

        if (!Schema::hasTable('purchase_rfq_vendor_rates')) {
            Schema::create('purchase_rfq_vendor_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('purchase_rfq_vendor_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('rate', 12, 2)->default(0.00);
                $table->decimal('quantity', 12, 4)->default(0.00);
                $table->date('delivery_date')->nullable();
                $table->date('validity_date')->nullable();
                $table->timestamps();

                $table->foreign('purchase_rfq_vendor_id')
                    ->references('id')
                    ->on('purchase_rfq_vendors')
                    ->onDelete('cascade');
                
                $table->index('tenant_id');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfq_vendor_rates');
        Schema::dropIfExists('purchase_rfq_vendors');
        Schema::dropIfExists('purchase_rfq_items');
        Schema::dropIfExists('purchase_rfqs');
    }
};
