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
        // 1. Leads
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'expected_amount')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->decimal('expected_amount', 15, 4)->nullable()->change();
            });
        }

        // 2. CRM Deals
        if (Schema::hasTable('crm_deals') && Schema::hasColumn('crm_deals', 'estimated_value')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                $table->decimal('estimated_value', 15, 4)->default(0.0000)->change();
            });
        }

        // 3. Quotations
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if (Schema::hasColumn('quotations', 'subtotal')) {
                    $table->decimal('subtotal', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('quotations', 'tax')) {
                    $table->decimal('tax', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('quotations', 'discount')) {
                    $table->decimal('discount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('quotations', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 4. Quotation Items
        if (Schema::hasTable('quotation_items')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                if (Schema::hasColumn('quotation_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 4)->change();
                }
                if (Schema::hasColumn('quotation_items', 'amount')) {
                    $table->decimal('amount', 15, 4)->change();
                }
            });
        }

        // 5. Sales Orders
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (Schema::hasColumn('sales_orders', 'subtotal')) {
                    $table->decimal('subtotal', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_orders', 'tax')) {
                    $table->decimal('tax', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_orders', 'discount')) {
                    $table->decimal('discount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_orders', 'shipping_charges')) {
                    $table->decimal('shipping_charges', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_orders', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_orders', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 6. Sales Order Items
        if (Schema::hasTable('sales_order_items')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 4)->change();
                }
                if (Schema::hasColumn('sales_order_items', 'discount')) {
                    $table->decimal('discount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_order_items', 'amount')) {
                    $table->decimal('amount', 15, 4)->change();
                }
            });
        }

        // 7. Invoices
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'subtotal')) {
                    $table->decimal('subtotal', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'amount_paid')) {
                    $table->decimal('amount_paid', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'balance_due')) {
                    $table->decimal('balance_due', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoices', 'freight_amount')) {
                    $table->decimal('freight_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 8. Invoice Items
        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 4)->change();
                }
                if (Schema::hasColumn('invoice_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoice_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoice_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoice_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoice_items', 'discount')) {
                    $table->decimal('discount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoice_items', 'subtotal')) {
                    $table->decimal('subtotal', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('invoice_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 9. Customer Payments
        if (Schema::hasTable('customer_payments') && Schema::hasColumn('customer_payments', 'amount')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }

        // 10. Payment Allocations
        if (Schema::hasTable('payment_allocations') && Schema::hasColumn('payment_allocations', 'allocated_amount')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->decimal('allocated_amount', 15, 4)->change();
            });
        }

        // 11. Sales Returns
        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                if (Schema::hasColumn('sales_returns', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('sales_returns', 'total_refund_amount')) {
                    $table->decimal('total_refund_amount', 15, 4)->default(0.0000)->change();
                }
            });
        }

        // 12. Sales Return Items
        if (Schema::hasTable('sales_return_items')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_return_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 4)->change();
                }
                if (Schema::hasColumn('sales_return_items', 'total_amount')) {
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
        // 1. Leads
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'expected_amount')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->decimal('expected_amount', 15, 2)->nullable()->change();
            });
        }

        // 2. CRM Deals
        if (Schema::hasTable('crm_deals') && Schema::hasColumn('crm_deals', 'estimated_value')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                $table->decimal('estimated_value', 15, 2)->default(0.00)->change();
            });
        }

        // 3. Quotations
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if (Schema::hasColumn('quotations', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('quotations', 'tax')) {
                    $table->decimal('tax', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('quotations', 'discount')) {
                    $table->decimal('discount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('quotations', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
            });
        }

        // 4. Quotation Items
        if (Schema::hasTable('quotation_items')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                if (Schema::hasColumn('quotation_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->change();
                }
                if (Schema::hasColumn('quotation_items', 'amount')) {
                    $table->decimal('amount', 15, 2)->change();
                }
            });
        }

        // 5. Sales Orders
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (Schema::hasColumn('sales_orders', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_orders', 'tax')) {
                    $table->decimal('tax', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_orders', 'discount')) {
                    $table->decimal('discount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_orders', 'shipping_charges')) {
                    $table->decimal('shipping_charges', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_orders', 'freight_amount')) {
                    $table->decimal('freight_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_orders', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
            });
        }

        // 6. Sales Order Items
        if (Schema::hasTable('sales_order_items')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->change();
                }
                if (Schema::hasColumn('sales_order_items', 'discount')) {
                    $table->decimal('discount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_order_items', 'amount')) {
                    $table->decimal('amount', 15, 2)->change();
                }
            });
        }

        // 7. Invoices
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'amount_paid')) {
                    $table->decimal('amount_paid', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'balance_due')) {
                    $table->decimal('balance_due', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoices', 'freight_amount')) {
                    $table->decimal('freight_amount', 12, 2)->default(0.00)->change();
                }
            });
        }

        // 8. Invoice Items
        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->change();
                }
                if (Schema::hasColumn('invoice_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoice_items', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoice_items', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoice_items', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoice_items', 'discount')) {
                    $table->decimal('discount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoice_items', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('invoice_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
            });
        }

        // 9. Customer Payments
        if (Schema::hasTable('customer_payments') && Schema::hasColumn('customer_payments', 'amount')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->change();
            });
        }

        // 10. Payment Allocations
        if (Schema::hasTable('payment_allocations') && Schema::hasColumn('payment_allocations', 'allocated_amount')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->decimal('allocated_amount', 15, 2)->change();
            });
        }

        // 11. Sales Returns
        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                if (Schema::hasColumn('sales_returns', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('sales_returns', 'total_refund_amount')) {
                    $table->decimal('total_refund_amount', 15, 2)->default(0.00)->change();
                }
            });
        }

        // 12. Sales Return Items
        if (Schema::hasTable('sales_return_items')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_return_items', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->change();
                }
                if (Schema::hasColumn('sales_return_items', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0.00)->change();
                }
            });
        }
    }
};
