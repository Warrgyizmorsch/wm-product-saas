<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_remnant_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('remnant_id')->constrained('inventory_remnants')->cascadeOnDelete();
            $table->string('event_type', 30)->default('consumption'); // consumption, split
            
            // Context
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('production_order_operation_id')->nullable();
            $table->foreign('production_order_operation_id', 'irc_po_op_fk')
                ->references('id')->on('production_order_operations')->nullOnDelete();
            $table->foreignId('split_remnant_id')->nullable();
            $table->foreign('split_remnant_id', 'irc_split_rem_fk')
                ->references('id')->on('inventory_remnants')->nullOnDelete();
            
            // Movement quantities
            $table->decimal('consumed_quantity', 12, 4); // Quantity affected in canonical product UOM
            $table->decimal('consumed_length', 12, 4)->nullable(); // Length affected (for linear)
            $table->decimal('remaining_quantity', 12, 4); // Remnant remaining canonical qty after event
            $table->decimal('remaining_length', 12, 4)->nullable(); // Remnant remaining length after event
            
            // Valuation
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->decimal('total_cost', 14, 4)->default(0.0000);
            
            // Actor & Timestamp
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->string('notes', 500)->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'remnant_id', 'event_type'], 'rem_cons_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_remnant_consumptions');
    }
};
