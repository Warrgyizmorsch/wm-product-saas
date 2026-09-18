<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id');
            // Which PaymentGateway implementation created this order — lets
            // the same table serve any gateway, not just Razorpay.
            $table->string('gateway');
            $table->string('gateway_order_id')->unique();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('gateway_signature')->nullable();
            // Amount in paise (Razorpay's native unit), matching the amount
            // actually sent to the Orders API — never re-derive this from
            // Plan.price at verification time, since the plan's price could
            // change between order-creation and payment.
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('created'); // created | paid | failed
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
