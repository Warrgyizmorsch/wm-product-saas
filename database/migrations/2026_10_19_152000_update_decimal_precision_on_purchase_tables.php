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
        // 1. Vendors opening balance
        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'opening_balance')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 4)->default(0.0000)->change();
            });
        }

        // 2. Purchase Requisition Items estimated cost
        if (Schema::hasTable('purchase_requisition_items') && Schema::hasColumn('purchase_requisition_items', 'estimated_cost')) {
            Schema::table('purchase_requisition_items', function (Blueprint $table) {
                $table->decimal('estimated_cost', 15, 4)->default(0.0000)->change();
            });
        }

        // 3. Purchase RFQ Items estimated cost
        if (Schema::hasTable('purchase_rfq_items') && Schema::hasColumn('purchase_rfq_items', 'estimated_cost')) {
            Schema::table('purchase_rfq_items', function (Blueprint $table) {
                $table->decimal('estimated_cost', 15, 4)->default(0.0000)->change();
            });
        }

        // 4. Purchase RFQ Vendor Rates
        if (Schema::hasTable('purchase_rfq_vendor_rates') && Schema::hasColumn('purchase_rfq_vendor_rates', 'rate')) {
            Schema::table('purchase_rfq_vendor_rates', function (Blueprint $table) {
                $table->decimal('rate', 15, 4)->default(0.0000)->change();
            });
        }

        // 5. Purchase Orders
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_orders', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'subtotal')) {
                    $table->decimal('subtotal', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'grand_total')) {
                    $table->decimal('grand_total', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 6. Purchase Order Items
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_order_items', 'rate')) {
                    $table->decimal('rate', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'amount')) {
                    $table->decimal('amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 7. Purchase Advance Payments
        if (Schema::hasTable('purchase_advance_payments') && Schema::hasColumn('purchase_advance_payments', 'amount')) {
            Schema::table('purchase_advance_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }

        // 8. Vendor Bills
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                if (Schema::hasColumn('vendor_bills', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'adjustment')) {
                    $table->decimal('adjustment', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 9. Vendor Payments
        if (Schema::hasTable('vendor_payments') && Schema::hasColumn('vendor_payments', 'amount')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->default(0.0000)->change();
            });
        }

        // 10. Vendor Payment Allocations
        if (Schema::hasTable('vendor_payment_allocations') && Schema::hasColumn('vendor_payment_allocations', 'allocated_amount')) {
            Schema::table('vendor_payment_allocations', function (Blueprint $table) {
                $table->decimal('allocated_amount', 15, 4)->default(0.0000)->change();
            });
        }

        // 11. Purchase Returns
        if (Schema::hasTable('purchase_returns')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_returns', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('purchase_returns', 'total_refund_amount')) {
                    $table->decimal('total_refund_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 12. Purchase Return Items
        if (Schema::hasTable('purchase_return_items')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_return_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 4)->change();
                }
                if (Schema::hasColumn('purchase_return_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Vendors opening balance
        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'opening_balance')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 2)->default(0.00)->change();
            });
        }

        // 2. Purchase Requisition Items estimated cost
        if (Schema::hasTable('purchase_requisition_items') && Schema::hasColumn('purchase_requisition_items', 'estimated_cost')) {
            Schema::table('purchase_requisition_items', function (Blueprint $table) {
                $table->decimal('estimated_cost', 12, 2)->default(0.00)->change();
            });
        }

        // 3. Purchase RFQ Items estimated cost
        if (Schema::hasTable('purchase_rfq_items') && Schema::hasColumn('purchase_rfq_items', 'estimated_cost')) {
            Schema::table('purchase_rfq_items', function (Blueprint $table) {
                $table->decimal('estimated_cost', 12, 2)->default(0.00)->change();
            });
        }

        // 4. Purchase RFQ Vendor Rates
        if (Schema::hasTable('purchase_rfq_vendor_rates') && Schema::hasColumn('purchase_rfq_vendor_rates', 'rate')) {
            Schema::table('purchase_rfq_vendor_rates', function (Blueprint $table) {
                $table->decimal('rate', 12, 2)->default(0.00)->change();
            });
        }

        // 5. Purchase Orders
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_orders', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'subtotal')) {
                    $table->decimal('subtotal', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'igst_amount')) {
                    $table->decimal('igst_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'tax_amount')) {
                    $table->decimal('tax_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_orders', 'grand_total')) {
                    $table->decimal('grand_total', 12, 2)->default(0.00)->change();
                }
            });
        }

        // 6. Purchase Order Items
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_order_items', 'rate')) {
                    $table->decimal('rate', 12, 2)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'amount')) {
                    $table->decimal('amount', 12, 2)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_order_items', 'total_amount')) {
                    $table->decimal('total_amount', 12, 2)->default(0.00)->change();
                }
            });
        }

        // 7. Purchase Advance Payments
        if (Schema::hasTable('purchase_advance_payments') && Schema::hasColumn('purchase_advance_payments', 'amount')) {
            Schema::table('purchase_advance_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->change();
            });
        }

        // 8. Vendor Bills
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                if (Schema::hasColumn('vendor_bills', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'adjustment')) {
                    $table->decimal('adjustment', 14, 2)->default(0.00)->change();
                }
            });
        }

        // 9. Vendor Payments
        if (Schema::hasTable('vendor_payments') && Schema::hasColumn('vendor_payments', 'amount')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->default(0.00)->change();
            });
        }

        // 10. Vendor Payment Allocations
        if (Schema::hasTable('vendor_payment_allocations') && Schema::hasColumn('vendor_payment_allocations', 'allocated_amount')) {
            Schema::table('vendor_payment_allocations', function (Blueprint $table) {
                $table->decimal('allocated_amount', 15, 2)->default(0.00)->change();
            });
        }

        // 11. Purchase Returns
        if (Schema::hasTable('purchase_returns')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_returns', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('purchase_returns', 'total_refund_amount')) {
                    $table->decimal('total_refund_amount', 15, 2)->default(0.00)->change();
                }
            });
        }

        // 12. Purchase Return Items
        if (Schema::hasTable('purchase_return_items')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_return_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->change();
                }
                if (Schema::hasColumn('purchase_return_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
            });
        }
    }
};
