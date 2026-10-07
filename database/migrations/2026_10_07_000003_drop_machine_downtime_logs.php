<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_machine_downtime_logs')) {
            Schema::drop('production_machine_downtime_logs');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_machine_downtime_logs')) {
            Schema::create('production_machine_downtime_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

                $table->unsignedBigInteger('downtime_id');
                $table->foreign('downtime_id', 'pmdl_downtime_fk')->references('id')->on('production_machine_downtimes')->cascadeOnDelete();

                $table->unsignedBigInteger('machine_id')->nullable();
                $table->foreign('machine_id', 'pmdl_machine_fk')->references('id')->on('production_machines')->nullOnDelete();

                $table->unsignedBigInteger('work_order_id')->nullable();
                $table->foreign('work_order_id', 'pmdl_work_order_fk')->references('id')->on('production_maintenance_work_orders')->nullOnDelete();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id', 'pmdl_user_fk')->references('id')->on('users')->nullOnDelete();

                $table->string('event_type');
                $table->string('summary');
                $table->json('details')->nullable();
                $table->string('source')->default('system');
                $table->string('status')->default('logged');
                $table->timestamp('logged_at')->useCurrent();
                $table->timestamps();

                $table->index(['tenant_id', 'downtime_id']);
                $table->index(['tenant_id', 'machine_id']);
                $table->index(['tenant_id', 'work_order_id']);
            });
        }
    }
};
