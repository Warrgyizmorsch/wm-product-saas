<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_operation_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'fk_pood_tenant_id')->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders', indexName: 'fk_pood_prod_order_id')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('production_order_operations', indexName: 'fk_pood_op_id')->cascadeOnDelete();
            $table->foreignId('predecessor_operation_id')->constrained('production_order_operations', indexName: 'fk_pood_pred_op_id')->cascadeOnDelete();
            $table->string('dependency_type')->default('cross_assembly');
            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'idx_poop_dep_order');
            $table->unique(['operation_id', 'predecessor_operation_id'], 'unique_op_dep');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operation_dependencies');
    }
};
