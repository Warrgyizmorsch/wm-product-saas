<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('routing_id')
                ->constrained('routings')
                ->cascadeOnDelete();

            // Sequence controls execution order: 10, 20, 30...
            $table->integer('sequence');
            $table->string('operation_number', 50)
                ->comment('Human label e.g. OP-010, OP-020. Derived from sequence * 10 by default.');

            $table->string('name');
            $table->text('description')->nullable();

            // Operation type for future module integration
            $table->string('operation_type')->default('manufacturing')
                ->comment('manufacturing | inspection | outsourcing | transport | maintenance');

            // Where and with what
            $table->foreignId('work_center_id')
                ->constrained('production_work_centers')
                ->restrictOnDelete()
                ->comment('Required: the work center where this operation executes.');

            $table->foreignId('machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete()
                ->comment('Optional: specific machine within the work center.');

            // Time components (all in minutes for precision)
            $table->decimal('setup_time_minutes', 10, 2)->default(0.00)
                ->comment('Time to prepare/configure the work center before processing starts.');
            $table->decimal('processing_time_minutes', 10, 2)->default(0.00)
                ->comment('Actual production/transformation time per unit.');
            $table->decimal('wait_time_minutes', 10, 2)->default(0.00)
                ->comment('Queuing / cooling / drying time after processing, before next operation.');

            // A2: Production loss tracking — yield percentage
            $table->decimal('expected_yield_percentage', 5, 2)->default(100.00)
                ->comment('A2: Expected good output percentage. e.g. 92.00 = 8% loss. Used in: effective_input_qty = output / (yield/100).');

            // Cost rates for RoutingCostService calculations
            $table->decimal('labor_cost_rate', 12, 4)->default(0.0000)
                ->comment('Labor cost per minute at this operation.');
            $table->decimal('machine_cost_rate', 12, 4)->default(0.0000)
                ->comment('Machine running cost per minute at this operation.');

            // A1: Document/instructions — inline now, attachment_path future
            $table->text('instructions')->nullable()
                ->comment('A1: Step-by-step work instructions. Future: production_operation_attachments table for file uploads.');

            $table->boolean('quality_required')->default(false)
                ->comment('Quality inspection checkpoint required at this operation.');

            // Subcontracting / external operation support
            $table->boolean('is_external')->default(false)
                ->comment('True if this operation is outsourced to a vendor.');
            $table->unsignedBigInteger('vendor_id')->nullable()
                ->comment('Future FK → vendors table when vendor module is available.');

            $table->timestamps();
            $table->softDeletes(); // A6: SoftDeletes — operation history must be preserved

            // Unique sequence per routing (application enforces this for soft-deleted rows)
            $table->unique(['routing_id', 'sequence'], 'routing_operations_routing_sequence_unique');
            $table->index(['tenant_id', 'routing_id']);
            $table->index(['work_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_routing_operations');
    }
};
