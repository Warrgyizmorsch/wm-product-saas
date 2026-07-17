<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_wips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()
                ->constrained('production_batches')
                ->nullOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('current_routing_operation_id')->nullable()
                ->constrained('production_routing_operations')
                ->nullOnDelete();
            $table->foreignId('current_schedule_operation_id')->nullable()
                ->constrained('production_schedule_operations')
                ->nullOnDelete();
            $table->foreignId('current_work_center_id')->nullable()
                ->constrained('production_work_centers')
                ->nullOnDelete();
            $table->foreignId('current_machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->decimal('available_quantity', 12, 4)->default(0.0000);
            $table->decimal('completed_quantity', 12, 4)->default(0.0000);
            $table->decimal('rejected_quantity', 12, 4)->default(0.0000);
            $table->decimal('scrap_quantity', 12, 4)->default(0.0000);
            $table->decimal('rework_quantity', 12, 4)->default(0.0000);

            $table->string('status')->default('active'); // active, quality_hold, rework, completed

            $table->decimal('material_cost', 12, 4)->default(0.0000);
            $table->decimal('labor_cost', 12, 4)->default(0.0000);
            $table->decimal('machine_cost', 12, 4)->default(0.0000);
            $table->decimal('overhead_cost', 12, 4)->default(0.0000);
            $table->decimal('total_value', 12, 4)->default(0.0000);

            $table->dateTime('started_at')->nullable();
            $table->dateTime('last_moved_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Indexes for tenancy and core relationships
            $table->index(['tenant_id', 'production_order_id'], 'wips_tenant_order_idx');
            $table->index(['tenant_id', 'status']);
            $table->index(['product_id']);
        });

        Schema::create('production_wip_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wip_id')
                ->constrained('production_wips')
                ->restrictOnDelete();
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()
                ->constrained('production_batches')
                ->nullOnDelete();

            $table->foreignId('from_operation_id')->nullable()
                ->constrained('production_routing_operations')
                ->nullOnDelete();
            $table->foreignId('to_operation_id')->nullable()
                ->constrained('production_routing_operations')
                ->nullOnDelete();
            $table->foreignId('from_work_center_id')->nullable()
                ->constrained('production_work_centers')
                ->nullOnDelete();
            $table->foreignId('to_work_center_id')->nullable()
                ->constrained('production_work_centers')
                ->nullOnDelete();
            $table->foreignId('machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();
            $table->unsignedBigInteger('operator_id')->nullable();

            $table->string('transaction_type'); // created, operation_started, operation_completed, transferred, adjusted, sent_to_quality, quality_approved, quality_rejected, sent_to_rework, rework_completed, scrapped, converted_to_fg

            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->decimal('good_quantity', 12, 4)->default(0.0000);
            $table->decimal('rejected_quantity', 12, 4)->default(0.0000);
            $table->decimal('scrap_quantity', 12, 4)->default(0.0000);
            $table->decimal('rework_quantity', 12, 4)->default(0.0000);

            $table->decimal('cost_before', 12, 4)->default(0.0000);
            $table->decimal('cost_added', 12, 4)->default(0.0000);
            $table->decimal('cost_after', 12, 4)->default(0.0000);

            $table->text('remarks')->nullable();
            $table->dateTime('transaction_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Indexes for quick lookups
            $table->index(['tenant_id', 'wip_id'], 'wip_tx_tenant_wip_idx');
            $table->index(['tenant_id', 'transaction_type']);
            $table->index(['production_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_wip_transactions');
        Schema::dropIfExists('production_wips');
    }
};
