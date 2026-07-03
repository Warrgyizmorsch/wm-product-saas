<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_reworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('production_order_operation_id')->nullable()
                ->constrained('production_order_operations')
                ->nullOnDelete();

            $table->decimal('quantity', 12, 4);
            $table->string('reason')->nullable();
            $table->string('status')->default('pending'); // pending, completed

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_rew_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_reworks');
    }
};
