<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('routing_operation_id')->nullable()
                ->constrained('production_routing_operations')
                ->nullOnDelete();

            $table->foreignId('previous_operation_id')->nullable()
                ->constrained('production_order_operations')
                ->nullOnDelete();

            $table->integer('sequence');
            $table->string('operation_number');
            $table->string('name');

            $table->foreignId('work_center_id')
                ->constrained('production_work_centers')
                ->restrictOnDelete();

            $table->foreignId('machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->string('status')->default('waiting');

            $table->decimal('setup_time_planned', 10, 2)->default(0.00);
            $table->decimal('processing_time_planned', 10, 2)->default(0.00);
            $table->decimal('total_time_planned', 10, 2)->default(0.00);

            $table->decimal('setup_time_actual', 10, 2)->default(0.00);
            $table->decimal('processing_time_actual', 10, 2)->default(0.00);

            $table->dateTime('actual_start_time')->nullable();
            $table->dateTime('actual_end_time')->nullable();

            $table->decimal('quantity_produced', 12, 4)->default(0.0000);
            $table->decimal('quantity_rejected', 12, 4)->default(0.0000);
            $table->decimal('quantity_scrapped', 12, 4)->default(0.0000);

            $table->foreignId('machine_used_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->foreignId('operator_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_ops_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
    }
};
