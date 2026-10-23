<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_issues', function (Blueprint $table) {
            $table->foreignId('rework_order_id')
                ->nullable()
                ->after('production_order_id')
                ->constrained('production_rework_orders')
                ->nullOnDelete();

            $table->foreignId('rework_operation_id')
                ->nullable()
                ->after('rework_order_id')
                ->constrained('production_rework_operations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'rework_order_id'], 'po_iss_tenant_rework_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_issues', function (Blueprint $table) {
            $table->dropIndex('po_iss_tenant_rework_idx');
            $table->dropConstrainedForeignId('rework_operation_id');
            $table->dropConstrainedForeignId('rework_order_id');
        });
    }
};
