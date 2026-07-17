<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_requisition_slips')) {
            Schema::create('production_requisition_slips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
                $table->string('requisition_number')->unique();
                $table->string('status')->default('pending'); // pending, partial, completed, cancelled
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->date('requisition_date');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'production_order_id'], 'prod_req_slip_tenant_po_idx');
            });
        }

        if (!Schema::hasTable('production_requisition_slip_items')) {
            Schema::create('production_requisition_slip_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('production_requisition_slip_id')
                    ->constrained('production_requisition_slips', 'id', 'fk_req_slip_parent')
                    ->cascadeOnDelete();

                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

                $table->decimal('quantity_planned', 12, 4);
                $table->decimal('quantity_reserved', 12, 4)->default(0.0000);
                $table->decimal('quantity_issued', 12, 4)->default(0.0000);

                $table->foreignId('uom_id')->constrained('uoms');
                $table->timestamps();

                $table->index('production_requisition_slip_id', 'prod_req_slip_items_parent_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_requisition_slip_items');
        Schema::dropIfExists('production_requisition_slips');
    }
};
