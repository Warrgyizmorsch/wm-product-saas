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
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('sales_order_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('sales_orders')
                ->nullOnDelete();

            $table->foreignId('crm_deal_id')
                ->nullable()
                ->after('sales_order_id')
                ->constrained('crm_deals')
                ->nullOnDelete();

            $table->foreignId('client_contact_id')
                ->nullable()
                ->after('crm_deal_id')
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('cost_centers')
                ->nullOnDelete();

            $table->index(['tenant_id', 'sales_order_id'], 'projects_tenant_sales_order_idx');
            $table->index(['tenant_id', 'crm_deal_id'], 'projects_tenant_crm_deal_idx');
            $table->index(['tenant_id', 'cost_center_id'], 'projects_tenant_cost_center_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_tenant_sales_order_idx');
            $table->dropIndex('projects_tenant_crm_deal_idx');
            $table->dropIndex('projects_tenant_cost_center_idx');

            $table->dropConstrainedForeignId('sales_order_id');
            $table->dropConstrainedForeignId('crm_deal_id');
            $table->dropConstrainedForeignId('client_contact_id');
            $table->dropConstrainedForeignId('cost_center_id');
        });
    }
};
