<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedule_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_schedule_id')
                ->constrained('production_schedules')
                ->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            // Explicit short constraint name to avoid MySQL 64-char limit
            $table->unsignedBigInteger('production_order_operation_id');
            $table->foreign('production_order_operation_id', 'pso_op_id_fk')
                ->references('id')
                ->on('production_order_operations')
                ->cascadeOnDelete();

            $table->foreignId('work_center_id')
                ->constrained('production_work_centers')
                ->restrictOnDelete();

            $table->foreignId('machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->integer('sequence');
            $table->integer('priority')->default(1);

            // Planning timing (calculated by SchedulingService)
            $table->dateTime('planned_start');
            $table->dateTime('planned_finish');
            $table->decimal('planned_duration_minutes', 10, 2)->default(0.00);

            // Actual timing (populated by MesExecutionService)
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();

            // MES execution status (separate from schedule planning status)
            $table->string('status')->default('waiting');

            // Future integration placeholder columns
            $table->string('shift_code')->nullable();                   // Shift Planning
            $table->string('quality_checkpoint_status')->nullable();    // QA Integration
            $table->string('maintenance_hold_status')->nullable();      // Maintenance Integration

            $table->timestamps();

            $table->index(['tenant_id', 'production_schedule_id'], 'pso_tenant_schedule_idx');
            $table->index(['tenant_id', 'production_order_id'], 'pso_tenant_order_idx');
            $table->index(['tenant_id', 'status'], 'pso_tenant_status_idx');
            $table->index(['machine_id', 'status'], 'pso_machine_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedule_operations');
    }
};
