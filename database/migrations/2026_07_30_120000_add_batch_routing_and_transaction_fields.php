<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('production_batches', 'current_operation_id')) {
                $table->unsignedBigInteger('current_operation_id')->nullable()->after('product_id');
                $table->foreign('current_operation_id', 'pb_curr_op_fk')
                    ->references('id')
                    ->on('production_order_operations')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('production_batches', 'source_operation_id')) {
                $table->unsignedBigInteger('source_operation_id')->nullable()->after('current_operation_id');
                $table->foreign('source_operation_id', 'pb_src_op_fk')
                    ->references('id')
                    ->on('production_order_operations')
                    ->nullOnDelete();
            }
            $table->index(['tenant_id', 'production_order_id', 'status'], 'pb_tenant_order_status_idx');
        });

        Schema::table('production_order_progress_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_progress_logs', 'production_batch_id')) {
                $table->unsignedBigInteger('production_batch_id')->nullable()->after('operation_id');
                $table->foreign('production_batch_id', 'popl_batch_fk')
                    ->references('id')
                    ->on('production_batches')
                    ->nullOnDelete();
                $table->index(['tenant_id', 'production_batch_id', 'operation_id'], 'popl_tenant_batch_op_idx');
            }
        });

        Schema::table('production_order_scraps', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_scraps', 'production_batch_id')) {
                $table->unsignedBigInteger('production_batch_id')->nullable()->after('production_order_operation_id');
                $table->foreign('production_batch_id', 'pos_batch_fk')
                    ->references('id')
                    ->on('production_batches')
                    ->nullOnDelete();
                $table->index(['tenant_id', 'production_batch_id'], 'pos_tenant_batch_idx');
            }
        });

        Schema::table('production_order_reworks', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_reworks', 'production_batch_id')) {
                $table->unsignedBigInteger('production_batch_id')->nullable()->after('production_order_operation_id');
                $table->foreign('production_batch_id', 'por_batch_fk')
                    ->references('id')
                    ->on('production_batches')
                    ->nullOnDelete();
                $table->index(['tenant_id', 'production_batch_id', 'status'], 'por_tenant_batch_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_order_reworks', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_reworks', 'production_batch_id')) {
                $table->dropForeign('por_batch_fk');
                $table->dropIndex('por_tenant_batch_status_idx');
                $table->dropColumn('production_batch_id');
            }
        });

        Schema::table('production_order_scraps', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_scraps', 'production_batch_id')) {
                $table->dropForeign('pos_batch_fk');
                $table->dropIndex('pos_tenant_batch_idx');
                $table->dropColumn('production_batch_id');
            }
        });

        Schema::table('production_order_progress_logs', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_progress_logs', 'production_batch_id')) {
                $table->dropForeign('popl_batch_fk');
                $table->dropIndex('popl_tenant_batch_op_idx');
                $table->dropColumn('production_batch_id');
            }
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropIndex('pb_tenant_order_status_idx');
            if (Schema::hasColumn('production_batches', 'source_operation_id')) {
                $table->dropForeign('pb_src_op_fk');
                $table->dropColumn('source_operation_id');
            }
            if (Schema::hasColumn('production_batches', 'current_operation_id')) {
                $table->dropForeign('pb_curr_op_fk');
                $table->dropColumn('current_operation_id');
            }
        });
    }
};
