<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_requisition_items', function (Blueprint $table) {
            $table->foreignId('project_task_id')
                ->nullable()
                ->after('sales_order_item_id')
                ->constrained('project_tasks')
                ->nullOnDelete();

            $table->index(['tenant_id', 'project_task_id'], 'pr_items_tenant_project_task_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requisition_items', function (Blueprint $table) {
            $table->dropIndex('pr_items_tenant_project_task_idx');
            $table->dropConstrainedForeignId('project_task_id');
        });
    }
};
