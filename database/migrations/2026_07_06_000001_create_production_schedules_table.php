<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('schedule_number');
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            // Scheduling strategy: forward only now, backward/manual future
            $table->string('scheduling_type')->default('forward');

            // Schedule planning lifecycle (never execution states like running/paused)
            $table->string('status')->default('draft');

            // Workflow timestamps
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            // Workflow actors
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'schedule_number'], 'production_schedules_tenant_number_unique');
            $table->index(['tenant_id', 'status'], 'production_schedules_tenant_status_idx');
            $table->index(['tenant_id', 'production_order_id'], 'production_schedules_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedules');
    }
};
