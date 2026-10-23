<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Purchase Requisitions
        if (Schema::hasTable('purchase_requisitions')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                try {
                    $table->dropUnique('purchase_requisitions_requisition_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'requisition_number'], 'purch_req_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 2. Purchase Orders
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('purchase_orders_purchase_order_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'purchase_order_number'], 'purch_ord_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 3. Purchase RFQs
        if (Schema::hasTable('purchase_rfqs')) {
            Schema::table('purchase_rfqs', function (Blueprint $table) {
                try {
                    $table->dropUnique('purchase_rfqs_rfq_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'rfq_number'], 'purch_rfq_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_requisitions')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                try {
                    $table->dropUnique('purch_req_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('requisition_number', 'purchase_requisitions_requisition_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('purch_ord_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('purchase_order_number', 'purchase_orders_purchase_order_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('purchase_rfqs')) {
            Schema::table('purchase_rfqs', function (Blueprint $table) {
                try {
                    $table->dropUnique('purch_rfq_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('rfq_number', 'purchase_rfqs_rfq_number_unique');
                } catch (\Throwable) {
                }
            });
        }
    }
};
