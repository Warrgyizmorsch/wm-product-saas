<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add maintenance tracking columns to production_machines if they don't exist
        Schema::table('production_machines', function (Blueprint $table) {
            if (!Schema::hasColumn('production_machines', 'last_maintenance_date')) {
                $table->date('last_maintenance_date')->nullable()->after('maintenance_status');
            }
            if (!Schema::hasColumn('production_machines', 'next_maintenance_due_date')) {
                $table->date('next_maintenance_due_date')->nullable()->after('last_maintenance_date');
            }
        });

        // 2. Create production_pm_schedules table
        if (!Schema::hasTable('production_pm_schedules')) {
            Schema::create('production_pm_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('machine_id');
                $table->string('name');
                $table->string('code');
                $table->enum('maintenance_type', ['preventive', 'calibration', 'inspection'])->default('preventive');
                $table->enum('frequency_type', ['days', 'weeks', 'months'])->default('days');
                $table->unsignedInteger('frequency_value')->default(30);
                $table->date('last_completed_date')->nullable();
                $table->date('next_due_date');
                $table->decimal('estimated_duration_hours', 8, 2)->default(1.00);
                $table->json('checklist_json')->nullable();
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id', 'fk_pms_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('machine_id', 'fk_pms_machine')->references('id')->on('production_machines')->cascadeOnDelete();

                $table->index(['tenant_id', 'next_due_date', 'is_active'], 'idx_pm_tenant_due');
                $table->unique(['tenant_id', 'code'], 'uniq_pm_tenant_code');
            });
        }

        // 3. Create production_maintenance_work_orders table
        if (!Schema::hasTable('production_maintenance_work_orders')) {
            Schema::create('production_maintenance_work_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('work_order_number');
                $table->unsignedBigInteger('machine_id');
                $table->unsignedBigInteger('pm_schedule_id')->nullable();
                $table->enum('type', ['preventive', 'breakdown', 'calibration'])->default('preventive');
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->unsignedBigInteger('assigned_technician_id')->nullable();
                $table->dateTime('planned_start')->nullable();
                $table->dateTime('planned_end')->nullable();
                $table->dateTime('actual_start')->nullable();
                $table->dateTime('actual_end')->nullable();
                $table->text('problem_description')->nullable();
                $table->text('work_performed')->nullable();
                $table->json('checklist_json')->nullable();
                $table->decimal('labor_hours', 8, 2)->default(0.00);
                $table->decimal('labor_cost_rate', 12, 2)->default(0.00);
                $table->decimal('labor_cost', 12, 2)->default(0.00);
                $table->decimal('spare_parts_cost', 12, 2)->default(0.00);
                $table->decimal('total_cost', 12, 2)->default(0.00);
                $table->unsignedBigInteger('downtime_id')->nullable();
                $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('draft');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'fk_pmwo_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('machine_id', 'fk_pmwo_machine')->references('id')->on('production_machines')->cascadeOnDelete();
                $table->foreign('pm_schedule_id', 'fk_pmwo_pms')->references('id')->on('production_pm_schedules')->nullOnDelete();
                $table->foreign('assigned_technician_id', 'fk_pmwo_tech')->references('id')->on('users')->nullOnDelete();
                $table->foreign('downtime_id', 'fk_pmwo_dt')->references('id')->on('production_machine_downtimes')->nullOnDelete();
                $table->foreign('created_by', 'fk_pmwo_creator')->references('id')->on('users')->nullOnDelete();
                $table->foreign('completed_by', 'fk_pmwo_completer')->references('id')->on('users')->nullOnDelete();

                $table->index(['tenant_id', 'status', 'machine_id'], 'idx_mwo_tenant_status');
                $table->unique(['tenant_id', 'work_order_number'], 'uniq_mwo_tenant_number');
            });
        }

        // 4. Create production_maintenance_work_order_spares table
        if (!Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::create('production_maintenance_work_order_spares', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('maintenance_work_order_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id');
                $table->decimal('requested_qty', 12, 4)->default(1.0000);
                $table->decimal('issued_qty', 12, 4)->default(0.0000);
                $table->decimal('unit_cost', 12, 2)->default(0.00);
                $table->decimal('total_cost', 12, 2)->default(0.00);
                $table->unsignedBigInteger('stock_transaction_id')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'fk_pmwos_tenant')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('maintenance_work_order_id', 'fk_pmwos_mwo')->references('id')->on('production_maintenance_work_orders')->cascadeOnDelete();
                $table->foreign('product_id', 'fk_pmwos_product')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('warehouse_id', 'fk_pmwos_wh')->references('id')->on('warehouses')->cascadeOnDelete();
                $table->foreign('stock_transaction_id', 'fk_pmwos_st')->references('id')->on('stock_transactions')->nullOnDelete();

                $table->index(['tenant_id', 'maintenance_work_order_id'], 'idx_mwo_spares_mwo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_maintenance_work_order_spares');
        Schema::dropIfExists('production_maintenance_work_orders');
        Schema::dropIfExists('production_pm_schedules');

        Schema::table('production_machines', function (Blueprint $table) {
            if (Schema::hasColumn('production_machines', 'next_maintenance_due_date')) {
                $table->dropColumn('next_maintenance_due_date');
            }
            if (Schema::hasColumn('production_machines', 'last_maintenance_date')) {
                $table->dropColumn('last_maintenance_date');
            }
        });
    }
};
