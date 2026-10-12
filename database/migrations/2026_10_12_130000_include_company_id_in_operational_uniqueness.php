<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (\Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        $tables = [
            'dispatch_orders' => [
                'drop' => ['disp_orders_tenant_branch_num_uniq', 'dispatch_orders_dispatch_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'dispatch_number'],
                'new' => 'disp_orders_tenant_co_branch_num_uniq',
            ],
            'goods_receipt_notes' => [
                'drop' => ['grn_tenant_branch_num_uniq', 'goods_receipt_notes_grn_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'grn_number'],
                'new' => 'grn_tenant_co_branch_num_uniq',
            ],
            'vendor_bills' => [
                'drop' => ['vendor_bills_tenant_branch_num_uniq', 'vendor_bills_bill_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'bill_number'],
                'new' => 'vendor_bills_tenant_co_branch_num_uniq',
            ],
            'vendor_payments' => [
                'drop' => ['vendor_payments_tenant_branch_num_uniq', 'vendor_payments_payment_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'payment_number'],
                'new' => 'vendor_payments_tenant_co_branch_num_uniq',
            ],
            'stock_transfers' => [
                'drop' => ['stock_transfers_tenant_branch_num_uniq', 'stock_transfers_transfer_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'transfer_number'],
                'new' => 'stock_transfers_tenant_co_branch_num_uniq',
            ],
            'stock_adjustments' => [
                'drop' => ['stock_adjustments_tenant_branch_num_uniq', 'stock_adjustments_adjustment_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'adjustment_number'],
                'new' => 'stock_adjustments_tenant_co_branch_num_uniq',
            ],
            'purchase_requisitions' => [
                'drop' => ['purch_req_tenant_branch_num_uniq', 'purchase_requisitions_requisition_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'requisition_number'],
                'new' => 'purch_req_tenant_co_branch_num_uniq',
            ],
            'purchase_orders' => [
                'drop' => ['purch_ord_tenant_branch_num_uniq', 'purchase_orders_purchase_order_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'purchase_order_number'],
                'new' => 'purch_ord_tenant_co_branch_num_uniq',
            ],
            'purchase_rfqs' => [
                'drop' => ['purch_rfq_tenant_branch_num_uniq', 'purchase_rfqs_rfq_number_unique'],
                'columns' => ['tenant_id', 'company_id', 'branch_id', 'rfq_number'],
                'new' => 'purch_rfq_tenant_co_branch_num_uniq',
            ],
        ];

        foreach ($tables as $table => $cfg) {
            if (!Schema::hasTable($table)) continue;

            foreach ($cfg['drop'] as $dropIdx) {
                if ($this->hasIndex($table, $dropIdx)) {
                    Schema::table($table, function (Blueprint $t) use ($dropIdx) {
                        $t->dropUnique($dropIdx);
                    });
                }
            }

            if (!$this->hasIndex($table, $cfg['new'])) {
                Schema::table($table, function (Blueprint $t) use ($cfg) {
                    $t->unique($cfg['columns'], $cfg['new']);
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'dispatch_orders' => [
                'current' => 'disp_orders_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'dispatch_number'],
                'restore_name' => 'disp_orders_tenant_branch_num_uniq',
            ],
            'goods_receipt_notes' => [
                'current' => 'grn_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'grn_number'],
                'restore_name' => 'grn_tenant_branch_num_uniq',
            ],
            'vendor_bills' => [
                'current' => 'vendor_bills_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'bill_number'],
                'restore_name' => 'vendor_bills_tenant_branch_num_uniq',
            ],
            'vendor_payments' => [
                'current' => 'vendor_payments_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'payment_number'],
                'restore_name' => 'vendor_payments_tenant_branch_num_uniq',
            ],
            'stock_transfers' => [
                'current' => 'stock_transfers_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'transfer_number'],
                'restore_name' => 'stock_transfers_tenant_branch_num_uniq',
            ],
            'stock_adjustments' => [
                'current' => 'stock_adjustments_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'adjustment_number'],
                'restore_name' => 'stock_adjustments_tenant_branch_num_uniq',
            ],
            'purchase_requisitions' => [
                'current' => 'purch_req_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'requisition_number'],
                'restore_name' => 'purch_req_tenant_branch_num_uniq',
            ],
            'purchase_orders' => [
                'current' => 'purch_ord_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'purchase_order_number'],
                'restore_name' => 'purch_ord_tenant_branch_num_uniq',
            ],
            'purchase_rfqs' => [
                'current' => 'purch_rfq_tenant_co_branch_num_uniq',
                'restore' => ['tenant_id', 'branch_id', 'rfq_number'],
                'restore_name' => 'purch_rfq_tenant_branch_num_uniq',
            ],
        ];

        foreach ($tables as $table => $cfg) {
            if (!Schema::hasTable($table)) continue;

            if ($this->hasIndex($table, $cfg['current'])) {
                Schema::table($table, function (Blueprint $t) use ($cfg) {
                    $t->dropUnique($cfg['current']);
                });
            }

            if (!$this->hasIndex($table, $cfg['restore_name'])) {
                Schema::table($table, function (Blueprint $t) use ($cfg) {
                    $t->unique($cfg['restore'], $cfg['restore_name']);
                });
            }
        }
    }
};
