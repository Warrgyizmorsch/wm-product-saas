<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modify production_machines
        Schema::table('production_machines', function (Blueprint $table) {
            $table->string('current_state')->default('Idle');
            $table->string('current_state_reason')->nullable();
        });

        // 2. New Table: production_machine_state_histories
        Schema::create('production_machine_state_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('machine_id');
            $table->foreign('machine_id', 'pmsh_m_fk')->references('id')->on('production_machines')->cascadeOnDelete();

            $table->string('state');
            $table->string('reason')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable();
            $table->foreign('changed_by', 'pmsh_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'machine_id']);
        });

        // 3. New Table: production_machine_downtimes
        Schema::create('production_machine_downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('machine_id');
            $table->foreign('machine_id', 'pmd_m_fk')->references('id')->on('production_machines')->cascadeOnDelete();

            $table->unsignedBigInteger('work_center_id');
            $table->foreign('work_center_id', 'pmd_wc_fk')->references('id')->on('production_work_centers')->cascadeOnDelete();

            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->foreign('production_order_id', 'pmd_order_fk')->references('id')->on('production_orders')->nullOnDelete();

            $table->unsignedBigInteger('production_order_operation_id')->nullable();
            $table->foreign('production_order_operation_id', 'pmd_op_fk')->references('id')->on('production_order_operations')->nullOnDelete();

            $table->string('reason');
            $table->string('category');

            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->decimal('duration_minutes', 10, 2)->nullable();

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by', 'pmd_creator_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pmd_approver_fk')->references('id')->on('users')->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->string('status')->default('open'); // open | closed
            $table->timestamps();

            $table->index(['tenant_id', 'machine_id']);
        });

        // 4. New Table: production_event_timelines
        Schema::create('production_event_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->foreign('production_order_id', 'pet_order_fk')->references('id')->on('production_orders')->nullOnDelete();

            $table->unsignedBigInteger('production_order_operation_id')->nullable();
            $table->foreign('production_order_operation_id', 'pet_op_fk')->references('id')->on('production_order_operations')->nullOnDelete();

            $table->unsignedBigInteger('production_batch_id')->nullable();
            $table->foreign('production_batch_id', 'pet_batch_fk')->references('id')->on('production_batches')->nullOnDelete();

            $table->unsignedBigInteger('production_serial_number_id')->nullable();
            $table->foreign('production_serial_number_id', 'pet_sn_fk')->references('id')->on('production_serial_numbers')->nullOnDelete();

            $table->unsignedBigInteger('machine_id')->nullable();
            $table->foreign('machine_id', 'pet_m_fk')->references('id')->on('production_machines')->nullOnDelete();

            $table->unsignedBigInteger('operator_id')->nullable();
            $table->foreign('operator_id', 'pet_op_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->string('event_type');
            $table->string('title');
            $table->text('description');

            $table->string('severity')->default('info'); // info | success | warning | critical
            $table->string('event_source');

            $table->timestamp('event_time');
            
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->foreign('triggered_by', 'pet_trigger_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id']);
            $table->index(['tenant_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_event_timelines');
        Schema::dropIfExists('production_machine_downtimes');
        Schema::dropIfExists('production_machine_state_histories');

        Schema::table('production_machines', function (Blueprint $table) {
            $table->dropColumn(['current_state', 'current_state_reason']);
        });
    }
};
