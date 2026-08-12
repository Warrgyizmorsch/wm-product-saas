<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedule_scenario_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('scenario_id')->constrained('production_schedule_scenarios', 'id', 'fk_psso_scenario')->onDelete('cascade');
            $table->foreignId('source_schedule_operation_id')->constrained('production_schedule_operations', 'id', 'fk_psso_source_op')->onDelete('cascade');
            $table->foreignId('production_schedule_id')->constrained('production_schedules', 'id', 'fk_psso_schedule')->onDelete('cascade');
            $table->foreignId('production_order_id')->constrained('production_orders', 'id', 'fk_psso_order')->onDelete('cascade');
            $table->foreignId('production_order_operation_id')->nullable()->constrained('production_order_operations', 'id', 'fk_psso_order_op')->onDelete('set null');
            $table->foreignId('work_center_id')->constrained('production_work_centers', 'id', 'fk_psso_wc')->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained('production_machines', 'id', 'fk_psso_machine')->onDelete('set null');
            $table->integer('sequence');
            $table->integer('priority')->default(3);
            $table->dateTime('planned_start');
            $table->dateTime('planned_finish');
            $table->decimal('planned_duration_minutes', 10, 2);
            $table->string('status')->default('scheduled');
            $table->boolean('locked')->default(false);
            $table->boolean('manual_override')->default(false);
            $table->integer('source_version')->default(1);
            $table->json('scenario_metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scenario_id'], 'idx_psso_tenant_scenario');
            $table->index(['tenant_id', 'source_schedule_operation_id'], 'idx_psso_tenant_src_op');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedule_scenario_operations');
    }
};
