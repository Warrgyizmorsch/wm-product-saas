<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plan_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_plan_id')
                ->constrained('production_plans')
                ->cascadeOnDelete();

            $table->foreignId('routing_operation_id')->nullable()
                ->constrained('production_routing_operations')
                ->nullOnDelete();

            $table->integer('sequence');
            $table->string('operation_number', 50);
            $table->string('name');

            $table->foreignId('work_center_id')
                ->constrained('production_work_centers')
                ->restrictOnDelete();

            $table->foreignId('machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->decimal('setup_time_minutes', 10, 2)->default(0.00);
            $table->decimal('processing_time_minutes', 10, 2)->default(0.00);
            $table->decimal('total_time_minutes', 10, 2)->default(0.00);

            $table->timestamps();

            $table->index(['tenant_id', 'production_plan_id']);
            $table->index(['work_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_operations');
    }
};
