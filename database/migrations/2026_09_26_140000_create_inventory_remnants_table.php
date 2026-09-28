<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_remnants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('remnant_code', 50)->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('warehouse_location', 100)->nullable(); // e.g. Rack A-12 / Bin 4
            $table->string('measurement_type', 30)->default('linear'); // linear, sheet, weight, count
            $table->string('status', 30)->default('available')->index(); // available, partially_reserved, fully_reserved, consumed, scrapped, pending_confirmation
            
            // Traceability & Lineage
            $table->foreignId('parent_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('heat_number', 100)->nullable();
            $table->foreignId('parent_remnant_id')->nullable()->constrained('inventory_remnants')->nullOnDelete();
            $table->foreignId('source_production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('source_production_order_operation_id')->nullable()->constrained('production_order_operations')->nullOnDelete();
            
            // Dimensional & Physical Attributes
            $table->string('dimension_unit', 20)->default('mm');
            $table->decimal('initial_length', 12, 4)->nullable();
            $table->decimal('current_length', 12, 4)->nullable();
            $table->decimal('reserved_length', 12, 4)->default(0.0000);
            
            $table->decimal('initial_width', 12, 4)->nullable();
            $table->decimal('current_width', 12, 4)->nullable();
            $table->decimal('thickness', 12, 4)->nullable();
            
            $table->decimal('weight', 12, 4)->nullable();
            $table->string('weight_unit', 20)->nullable(); // kg, g
            $table->integer('pieces')->default(1);
            
            // Canonical Product Quantity & Cost
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->decimal('initial_quantity', 12, 4);
            $table->decimal('current_quantity', 12, 4);
            $table->decimal('reserved_quantity', 12, 4)->default(0.0000);
            $table->decimal('unit_cost', 14, 4)->default(0.0000); // Cost per canonical unit
            
            // Approvals & Audit
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'remnant_code'], 'inv_remnants_tenant_code_unique');
            $table->index(['tenant_id', 'product_id', 'status'], 'inv_remnants_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_remnants');
    }
};
