<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_maintenance_work_order_logs')) {
            return;
        }

        Schema::create('production_maintenance_work_order_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('downtime_id')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('event_type');
            $table->string('summary');
            $table->json('details')->nullable();
            $table->string('source')->default('system');
            $table->string('status')->default('logged');
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_mwol_tenant')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('work_order_id', 'fk_mwol_work_order')->references('id')->on('production_maintenance_work_orders')->nullOnDelete();
            $table->foreign('downtime_id', 'fk_mwol_downtime')->references('id')->on('production_machine_downtimes')->nullOnDelete();
            $table->foreign('machine_id', 'fk_mwol_machine')->references('id')->on('production_machines')->nullOnDelete();
            $table->foreign('user_id', 'fk_mwol_user')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'work_order_id'], 'idx_mwol_tenant_work_order');
            $table->index(['tenant_id', 'machine_id'], 'idx_mwol_tenant_machine');
            $table->index(['tenant_id', 'downtime_id'], 'idx_mwol_tenant_downtime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_maintenance_work_order_logs');
    }
};
