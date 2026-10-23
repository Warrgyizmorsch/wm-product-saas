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
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 4)->default(0)->change();
                $table->decimal('tax_amount', 15, 4)->default(0)->change();
                $table->decimal('grand_total', 15, 4)->default(0)->change();
                $table->decimal('paid_amount', 15, 4)->default(0)->change();
                $table->decimal('due_amount', 15, 4)->default(0)->change();
                if (Schema::hasColumn('vendor_bills', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 4)->default(0)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 4)->default(0)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 4)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('vendor_bill_items')) {
            Schema::table('vendor_bill_items', function (Blueprint $table) {
                $table->decimal('unit_rate', 15, 4)->default(0)->change();
                $table->decimal('total_amount', 15, 4)->default(0)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 2)->default(0)->change();
                $table->decimal('tax_amount', 15, 2)->default(0)->change();
                $table->decimal('grand_total', 15, 2)->default(0)->change();
                $table->decimal('paid_amount', 15, 2)->default(0)->change();
                $table->decimal('due_amount', 15, 2)->default(0)->change();
                if (Schema::hasColumn('vendor_bills', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 15, 2)->default(0)->change();
                }
                if (Schema::hasColumn('vendor_bills', 'igst_amount')) {
                    $table->decimal('igst_amount', 15, 2)->default(0)->change();
                }
            });
        }

        if (Schema::hasTable('vendor_bill_items')) {
            Schema::table('vendor_bill_items', function (Blueprint $table) {
                $table->decimal('unit_rate', 15, 2)->default(0)->change();
                $table->decimal('total_amount', 15, 2)->default(0)->change();
            });
        }
    }
};
