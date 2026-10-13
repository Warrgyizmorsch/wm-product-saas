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
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->foreignId('sales_order_item_id')
                ->nullable()
                ->after('project_id')
                ->constrained('sales_order_items')
                ->nullOnDelete();

            $table->index(['tenant_id', 'sales_order_item_id'], 'project_milestones_tenant_so_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->dropIndex('project_milestones_tenant_so_item_idx');
            $table->dropConstrainedForeignId('sales_order_item_id');
        });
    }
};
