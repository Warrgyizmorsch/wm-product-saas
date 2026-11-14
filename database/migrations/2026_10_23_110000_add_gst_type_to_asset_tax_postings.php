<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Intra-state (cgst_sgst) or inter-state (igst), so asset GST reaches
        // the CGST/SGST or IGST ledgers instead of the Duties & Taxes header.
        Schema::table('assets', function (Blueprint $table) {
            $table->string('recoverable_tax_gst_type', 20)->nullable()->after('recoverable_tax');
        });

        Schema::table('asset_disposals', function (Blueprint $table) {
            $table->string('gst_type', 20)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('recoverable_tax_gst_type'));
        Schema::table('asset_disposals', fn (Blueprint $table) => $table->dropColumn('gst_type'));
    }
};
