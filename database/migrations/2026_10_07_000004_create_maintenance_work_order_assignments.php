<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_maintenance_work_order_assignments')) {
            return;
        }

        Schema::create('production_maintenance_work_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->enum('assignment_type', ['internal', 'external'])->default('internal');
            $table->dateTime('assigned_at')->nullable();
            $table->decimal('worked_hours', 8, 2)->default(0.00);
            $table->decimal('hourly_rate', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_pmwta_tenant')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_order_id', 'fk_pmwta_work_order')->references('id')->on('production_maintenance_work_orders')->cascadeOnDelete();
            $table->foreign('technician_id', 'fk_pmwta_technician')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'work_order_id'], 'idx_pmwta_tenant_work_order');
            $table->index(['tenant_id', 'assignment_type'], 'idx_pmwta_tenant_type');
            $table->index(['tenant_id', 'technician_id'], 'idx_pmwta_tenant_technician');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_maintenance_work_order_assignments');
    }
};
