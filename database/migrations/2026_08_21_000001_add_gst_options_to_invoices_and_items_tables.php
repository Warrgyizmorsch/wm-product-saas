<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE invoices MODIFY sales_order_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Ignore if driver does not support raw modify statement
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'gst_type')) {
                $table->string('gst_type')->default('cgst_sgst')->after('tax_amount');
            }
            if (!Schema::hasColumn('invoices', 'cgst_amount')) {
                $table->decimal('cgst_amount', 15, 2)->default(0.00)->after('gst_type');
            }
            if (!Schema::hasColumn('invoices', 'sgst_amount')) {
                $table->decimal('sgst_amount', 15, 2)->default(0.00)->after('cgst_amount');
            }
            if (!Schema::hasColumn('invoices', 'igst_amount')) {
                $table->decimal('igst_amount', 15, 2)->default(0.00)->after('sgst_amount');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'cgst_percent')) {
                $table->decimal('cgst_percent', 15, 2)->default(0.00)->after('tax_amount');
            }
            if (!Schema::hasColumn('invoice_items', 'sgst_percent')) {
                $table->decimal('sgst_percent', 15, 2)->default(0.00)->after('cgst_percent');
            }
            if (!Schema::hasColumn('invoice_items', 'igst_percent')) {
                $table->decimal('igst_percent', 15, 2)->default(0.00)->after('sgst_percent');
            }
            if (!Schema::hasColumn('invoice_items', 'cgst_amount')) {
                $table->decimal('cgst_amount', 15, 2)->default(0.00)->after('igst_percent');
            }
            if (!Schema::hasColumn('invoice_items', 'sgst_amount')) {
                $table->decimal('sgst_amount', 15, 2)->default(0.00)->after('cgst_amount');
            }
            if (!Schema::hasColumn('invoice_items', 'igst_amount')) {
                $table->decimal('igst_amount', 15, 2)->default(0.00)->after('sgst_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('invoices', 'gst_type') ? 'gst_type' : null,
                Schema::hasColumn('invoices', 'cgst_amount') ? 'cgst_amount' : null,
                Schema::hasColumn('invoices', 'sgst_amount') ? 'sgst_amount' : null,
                Schema::hasColumn('invoices', 'igst_amount') ? 'igst_amount' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('invoice_items', 'cgst_percent') ? 'cgst_percent' : null,
                Schema::hasColumn('invoice_items', 'sgst_percent') ? 'sgst_percent' : null,
                Schema::hasColumn('invoice_items', 'igst_percent') ? 'igst_percent' : null,
                Schema::hasColumn('invoice_items', 'cgst_amount') ? 'cgst_amount' : null,
                Schema::hasColumn('invoice_items', 'sgst_amount') ? 'sgst_amount' : null,
                Schema::hasColumn('invoice_items', 'igst_amount') ? 'igst_amount' : null,
            ]);
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
