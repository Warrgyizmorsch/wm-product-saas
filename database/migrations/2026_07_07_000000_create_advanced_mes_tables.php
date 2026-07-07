<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alterations for Parallel Operations and Production Mode
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->string('parallel_group')->nullable();
            $table->boolean('is_parallel')->default(false);
            $table->string('parallel_type')->default('AND'); // AND | OR
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->string('parallel_group')->nullable();
            $table->boolean('is_parallel')->default(false);
            $table->string('parallel_type')->default('AND'); // AND | OR
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->string('production_mode')->default('standard'); // standard | batch | serial | batch_and_serial
            $table->string('barcode')->nullable();
            $table->string('qr_code')->nullable();
        });

        // 2. New Table: Operator Skills / Qualifications
        Schema::create('production_operator_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('skill_code');
            
            $table->unsignedBigInteger('work_center_id')->nullable();
            $table->foreign('work_center_id', 'pos_wc_fk')->references('id')->on('production_work_centers')->nullOnDelete();

            $table->unsignedBigInteger('machine_id')->nullable();
            $table->foreign('machine_id', 'pos_m_fk')->references('id')->on('production_machines')->nullOnDelete();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });

        // 3. New Table: Operator Assignments
        Schema::create('production_operator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('production_order_operation_id');
            $table->foreign('production_order_operation_id', 'p_oa_order_op_fk')
                ->references('id')->on('production_order_operations')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('assigned_by');
            $table->foreign('assigned_by', 'p_oa_assigned_by_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->string('status')->default('assigned'); // assigned | accepted | rejected | completed
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 4. New Table: Operator Assignment Logs (Audit Trail)
        Schema::create('production_operator_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('operator_assignment_id');
            $table->foreign('operator_assignment_id', 'p_oal_assign_fk')
                ->references('id')->on('production_operator_assignments')->cascadeOnDelete();

            $table->unsignedBigInteger('previous_operator_id')->nullable();
            $table->foreign('previous_operator_id', 'p_oal_prev_op_fk')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('new_operator_id');
            $table->foreign('new_operator_id', 'p_oal_new_op_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->string('action'); // assigned | reassigned | accepted | rejected | completed
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('changed_by');
            $table->foreign('changed_by', 'p_oal_changer_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->timestamps();
        });

        // 5. New Table: Production Batches
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');

            $table->unsignedBigInteger('production_order_id');
            $table->foreign('production_order_id', 'pb_order_fk')
                ->references('id')->on('production_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'pb_product_fk')
                ->references('id')->on('products')->cascadeOnDelete();

            $table->decimal('planned_quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4)->default(0.0000);
            
            $table->timestamp('manufactured_at')->nullable();
            $table->date('expiry_date')->nullable();
            
            $table->string('status')->default('planned'); // planned | in_progress | completed | cancelled | consumed | blocked | quarantine
            $table->text('remarks')->nullable();
            $table->string('barcode')->nullable();
            $table->string('qr_code')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'batch_number']);
        });

        // 6. New Table: Batch Genealogy Join Table (Splits / Merges)
        Schema::create('production_batch_genealogies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'p_bg_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedBigInteger('parent_batch_id');
            $table->foreign('parent_batch_id', 'p_bg_parent_fk')->references('id')->on('production_batches')->cascadeOnDelete();

            $table->unsignedBigInteger('child_batch_id');
            $table->foreign('child_batch_id', 'p_bg_child_fk')->references('id')->on('production_batches')->cascadeOnDelete();

            $table->string('type')->default('split'); // split | merge
            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->timestamps();
        });

        // 7. New Table: Serial Numbers
        Schema::create('production_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('production_order_id');
            $table->foreign('production_order_id', 'psn_order_fk')
                ->references('id')->on('production_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('batch_id')->nullable();
            $table->foreign('batch_id', 'psn_batch_fk')
                ->references('id')->on('production_batches')->nullOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'psn_product_fk')
                ->references('id')->on('products')->cascadeOnDelete();

            $table->string('serial_number');
            $table->timestamp('manufactured_at')->nullable();
            $table->string('status')->default('planned'); // planned | produced | packed | shipped | installed | returned | scrapped | reworked
            
            $table->string('barcode')->nullable();
            $table->string('qr_code')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'serial_number']);
        });

        // 8. New Table: Lot Traceability
        Schema::create('production_lot_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source_type'); // batch | order | serial | lot
            $table->unsignedBigInteger('source_id');
            $table->string('target_type'); // batch | order | serial | lot
            $table->unsignedBigInteger('target_id');
            $table->decimal('quantity', 12, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 9. New Table: QR/Barcode Scan Logs
        Schema::create('production_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('scan_type'); // material | batch | serial | production_order | operator | machine
            
            $table->unsignedBigInteger('scanned_by');
            $table->foreign('scanned_by', 'p_sl_user_fk')->references('id')->on('users')->cascadeOnDelete();

            $table->string('device_identifier')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_scan_logs');
        Schema::dropIfExists('production_lot_traces');
        Schema::dropIfExists('production_serial_numbers');
        Schema::dropIfExists('production_batch_genealogies');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('production_operator_assignment_logs');
        Schema::dropIfExists('production_operator_assignments');
        Schema::dropIfExists('production_operator_skills');

        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['production_mode', 'barcode', 'qr_code']);
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->dropColumn(['parallel_group', 'is_parallel', 'parallel_type']);
        });

        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->dropColumn(['parallel_group', 'is_parallel', 'parallel_type']);
        });
    }
};
