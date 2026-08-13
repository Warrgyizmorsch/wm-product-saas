<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('production_schedule_change_logs');
        Schema::create('production_schedule_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_schedule_id')
                ->constrained('production_schedules')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('production_schedule_operation_id');
            $table->foreign('production_schedule_operation_id', 'pscl_op_id_fk')
                ->references('id')
                ->on('production_schedule_operations')
                ->cascadeOnDelete();

            $table->string('change_type'); // e.g. manual_shift, machine_reassign, lock_toggle, ripple_shift, reschedule_start
            $table->string('shift_mode')->nullable(); // isolated, ripple

            $table->foreignId('old_machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->foreignId('new_machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->dateTime('old_planned_start')->nullable();
            $table->dateTime('new_planned_start')->nullable();
            $table->dateTime('old_planned_finish')->nullable();
            $table->dateTime('new_planned_finish')->nullable();

            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'production_schedule_id'], 'pscl_tenant_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedule_change_logs');
    }
};
