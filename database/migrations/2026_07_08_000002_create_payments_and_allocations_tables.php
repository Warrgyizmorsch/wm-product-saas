<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('payment_number')->index();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // Cash, Bank, Card, UPI, etc.
            $table->string('reference_no')->nullable(); // bank txn ID / check no
            $table->string('status')->default('Draft')->index(); // Draft, Confirmed, Cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'customer_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('customer_payments');
    }
};
