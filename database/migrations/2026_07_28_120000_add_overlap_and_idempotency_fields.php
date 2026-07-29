<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->boolean('overlap_enabled')->default(false)->after('sequence');
            $table->decimal('transfer_batch_quantity', 12, 4)->default(0.0000)->after('overlap_enabled');
            $table->integer('transfer_lag_minutes')->default(0)->after('transfer_batch_quantity');
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->boolean('overlap_enabled')->default(false)->after('sequence');
            $table->decimal('transfer_batch_quantity', 12, 4)->default(0.0000)->after('overlap_enabled');
            $table->integer('transfer_lag_minutes')->default(0)->after('transfer_batch_quantity');
            $table->decimal('quantity_transferred_out', 12, 4)->default(0.0000)->after('quantity_scrapped');
            $table->decimal('quantity_transferred_in', 12, 4)->default(0.0000)->after('quantity_transferred_out');
        });

        Schema::table('production_order_progress_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('tenant_id');
            $table->unique(['tenant_id', 'idempotency_key'], 'uniq_progress_log_idempotency');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_progress_logs', function (Blueprint $table) {
            $table->dropUnique('uniq_progress_log_idempotency');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->dropColumn([
                'overlap_enabled',
                'transfer_batch_quantity',
                'transfer_lag_minutes',
                'quantity_transferred_out',
                'quantity_transferred_in',
            ]);
        });

        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->dropColumn([
                'overlap_enabled',
                'transfer_batch_quantity',
                'transfer_lag_minutes',
            ]);
        });
    }
};
