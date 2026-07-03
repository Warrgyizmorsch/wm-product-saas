<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            
            // Nullable quotation_id (no FK constraint direct if SQLite compat is needed or do conditionally)
            $table->unsignedBigInteger('quotation_id')->nullable();
            
            $table->string('sales_order_number')->index();
            $table->date('order_date');
            $table->date('shipment_date')->nullable();
            $table->string('status')->default('Draft')->index(); // Draft, Confirmed, Shipped, Cancelled
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('payment_terms')->nullable();
            
            $table->unsignedBigInteger('sales_person_id')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('shipping_charges', 15, 2)->default(0.00);
            $table->decimal('adjustment', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'created_at']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('quotation_id')
                    ->references('id')
                    ->on('quotations')
                    ->nullOnDelete();

                $table->foreign('sales_person_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
