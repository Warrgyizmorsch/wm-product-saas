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
        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('sales_order_item_id')
                ->constrained('projects')
                ->nullOnDelete();

            $table->foreignId('project_milestone_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_milestones')
                ->nullOnDelete();

            $table->foreignId('project_task_id')
                ->nullable()
                ->after('project_milestone_id')
                ->constrained('project_tasks')
                ->nullOnDelete();

            $table->index(['tenant_id', 'project_id'], 'production_orders_tenant_project_idx');
            $table->index(['tenant_id', 'project_task_id'], 'production_orders_tenant_task_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropIndex('production_orders_tenant_project_idx');
            $table->dropIndex('production_orders_tenant_task_idx');

            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('project_milestone_id');
            $table->dropConstrainedForeignId('project_task_id');
        });
    }
};
