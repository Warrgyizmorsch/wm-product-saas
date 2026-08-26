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
        Schema::table('vendor_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_bills', 'gst_type')) {
                $table->string('gst_type')->default('cgst_sgst')->after('vendor_invoice_number');
            }
            if (!Schema::hasColumn('vendor_bills', 'freight_terms')) {
                $table->string('freight_terms')->nullable()->after('gst_type');
            }
            if (!Schema::hasColumn('vendor_bills', 'freight_amount')) {
                $table->decimal('freight_amount', 15, 2)->default(0)->after('freight_terms');
            }
            if (!Schema::hasColumn('vendor_bills', 'cgst_amount')) {
                $table->decimal('cgst_amount', 15, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('vendor_bills', 'sgst_amount')) {
                $table->decimal('sgst_amount', 15, 2)->default(0)->after('cgst_amount');
            }
            if (!Schema::hasColumn('vendor_bills', 'igst_amount')) {
                $table->decimal('igst_amount', 15, 2)->default(0)->after('sgst_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_bills', 'gst_type')) {
                $table->dropColumn(['gst_type', 'freight_terms', 'freight_amount', 'cgst_amount', 'sgst_amount', 'igst_amount']);
            }
        });
    }
};