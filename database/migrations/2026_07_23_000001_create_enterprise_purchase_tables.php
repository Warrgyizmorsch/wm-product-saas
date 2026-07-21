<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PO Advance Payments Table
        if (!Schema::hasTable('purchase_advance_payments')) {
            Schema::create('purchase_advance_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('payment_number')->unique();
                $table->date('payment_date');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method')->default('Bank Transfer');
                $table->string('reference_number')->nullable();
                $table->string('status')->default('Posted');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 2. Vendor Bills Table (Generated ONLY from Approved GRN)
        if (!Schema::hasTable('vendor_bills')) {
            Schema::create('vendor_bills', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('bill_number')->unique(); // BILL-2026-000001
                $table->string('vendor_invoice_number')->nullable();
                $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->date('bill_date');
                $table->date('due_date')->nullable();
                $table->string('status')->default('Posted'); // Draft, Posted, Partially Paid, Paid, Cancelled
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('due_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 3. Vendor Bill Items Table
        if (!Schema::hasTable('vendor_bill_items')) {
            Schema::create('vendor_bill_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('vendor_bill_id')->constrained('vendor_bills')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('goods_receipt_note_item_id')->nullable()->constrained('goods_receipt_note_items')->nullOnDelete();
                $table->decimal('quantity', 12, 3)->default(0);
                $table->decimal('unit_rate', 15, 2)->default(0);
                $table->decimal('tax_percentage', 5, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 4. Vendor Payments Table
        if (!Schema::hasTable('vendor_payments')) {
            Schema::create('vendor_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('payment_number')->unique(); // VPAY-2026-000001
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->string('payment_type')->default('Bill Payment'); // Advance, Bill Payment
                $table->string('payment_method')->default('Bank Transfer');
                $table->date('payment_date');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('reference_number')->nullable();
                $table->string('status')->default('Posted');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 5. Vendor Payment Allocations Table (N-to-N Payment to Bills Allocation)
        if (!Schema::hasTable('vendor_payment_allocations')) {
            Schema::create('vendor_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('vendor_payment_id')->constrained('vendor_payments')->cascadeOnDelete();
                $table->foreignId('vendor_bill_id')->constrained('vendor_bills')->cascadeOnDelete();
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_allocations');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bill_items');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('purchase_advance_payments');
    }
};
