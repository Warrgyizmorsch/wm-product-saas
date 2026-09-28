<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_remnant_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('production_order_reservation_id')->nullable();
            $table->foreign('production_order_reservation_id', 'pora_po_res_fk')
                ->references('id')->on('production_order_reservations')->cascadeOnDelete();
            $table->foreignId('remnant_id')->constrained('inventory_remnants')->cascadeOnDelete();
            
            $table->decimal('allocated_quantity', 12, 4);
            $table->decimal('allocated_length', 12, 4)->nullable();
            $table->string('status', 30)->default('reserved')->index(); // reserved, consumed, released
            
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'production_order_id', 'status'], 'po_rem_alloc_order_status_idx');
            $table->index(['tenant_id', 'remnant_id', 'status'], 'po_rem_alloc_remnant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_remnant_allocations');
    }
};
