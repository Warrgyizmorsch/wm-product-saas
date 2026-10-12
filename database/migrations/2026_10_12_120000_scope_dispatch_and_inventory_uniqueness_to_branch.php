<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dispatch Orders
        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('dispatch_orders_dispatch_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'dispatch_number'], 'disp_orders_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 2. Goods Receipt Notes
        if (Schema::hasTable('goods_receipt_notes')) {
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                try {
                    $table->dropUnique('goods_receipt_notes_grn_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'grn_number'], 'grn_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 3. Vendor Bills
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                try {
                    $table->dropUnique('vendor_bills_bill_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'bill_number'], 'vendor_bills_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 4. Vendor Payments
        if (Schema::hasTable('vendor_payments')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                try {
                    $table->dropUnique('vendor_payments_payment_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'payment_number'], 'vendor_payments_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 5. Stock Transfers
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                try {
                    $table->dropUnique('stock_transfers_transfer_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'transfer_number'], 'stock_transfers_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }

        // 6. Stock Adjustments
        if (Schema::hasTable('stock_adjustments')) {
            Schema::table('stock_adjustments', function (Blueprint $table) {
                try {
                    $table->dropUnique('stock_adjustments_adjustment_number_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->unique(['tenant_id', 'branch_id', 'adjustment_number'], 'stock_adjustments_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('disp_orders_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('dispatch_number', 'dispatch_orders_dispatch_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('goods_receipt_notes')) {
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                try {
                    $table->dropUnique('grn_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('grn_number', 'goods_receipt_notes_grn_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                try {
                    $table->dropUnique('vendor_bills_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('bill_number', 'vendor_bills_bill_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('vendor_payments')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                try {
                    $table->dropUnique('vendor_payments_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('payment_number', 'vendor_payments_payment_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                try {
                    $table->dropUnique('stock_transfers_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('transfer_number', 'stock_transfers_transfer_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('stock_adjustments')) {
            Schema::table('stock_adjustments', function (Blueprint $table) {
                try {
                    $table->dropUnique('stock_adjustments_tenant_branch_num_uniq');
                } catch (\Throwable) {
                }
                try {
                    $table->unique('adjustment_number', 'stock_adjustments_adjustment_number_unique');
                } catch (\Throwable) {
                }
            });
        }
    }
};
