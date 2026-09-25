<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded throughout: MySQL DDL isn't transactional, and this migration
        // once failed half-way on a MyISAM host (1000-byte index cap), leaving
        // some of these tables behind.

        // A tenant's recurring per-user subscription (Razorpay Subscriptions),
        // see TenantSubscriptionService. Amounts in paise.
        if (! Schema::hasTable('tenant_subscriptions')) Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('cycle');                       // monthly | yearly
            $table->unsignedInteger('seats');
            $table->json('modules')->nullable();           // recurring add-ons billed on it
            $table->unsignedBigInteger('subtotal');        // per cycle, excl. GST
            $table->unsignedBigInteger('gst');
            $table->unsignedBigInteger('total');           // what's charged each cycle
            $table->string('currency', 3)->default('INR');
            $table->string('gateway', 32);
            $table->string('gateway_plan_id')->nullable();
            $table->string('gateway_subscription_id')->nullable()->unique();
            // created → active → (pending|halted → active) → cancelled|completed
            $table->string('status')->default('created');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_start')->nullable();
            $table->timestamp('current_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // Razorpay plans are immutable: one per (period, per-seat amount), reused.
        // Short key columns keep the unique index under MyISAM's 1000-byte cap.
        // A leftover table from the failed run has no index and no rows; rebuild it.
        if (Schema::hasTable('gateway_plans') && ! Schema::hasIndex('gateway_plans', ['gateway', 'period', 'amount', 'currency'], 'unique')) {
            Schema::drop('gateway_plans');
        }
        if (! Schema::hasTable('gateway_plans')) Schema::create('gateway_plans', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('period', 16);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('gateway_plan_id');
            $table->timestamps();

            $table->unique(['gateway', 'period', 'amount', 'currency']);
        });

        // Subscription charges have no order of ours to key on.
        if (! Schema::hasColumn('subscription_payments', 'tenant_subscription_id')) Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('gateway_order_id')->nullable()->change();
            $table->foreignId('tenant_subscription_id')->nullable()->after('plan_id')
                ->constrained('tenant_subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_subscription_id');
        });
        Schema::dropIfExists('gateway_plans');
        Schema::dropIfExists('tenant_subscriptions');
    }
};
