<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSTR-2B reconciliation: the portal's GSTR-2B JSON is uploaded per return
 * period, each document becomes a line, and each line is matched against the
 * vendor bills in the books. Uses the existing accounting.gst_returns.view /
 * accounting.gst_returns.file permissions — no new ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gstr2b_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('return_period', 6); // MMYYYY, the portal's "rtnprd"
            $table->string('gstin', 15)->nullable();
            $table->date('generated_on')->nullable(); // portal "gendt"
            $table->string('file_name')->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->json('summary')->nullable();
            $table->dateTime('matched_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id', 'return_period'], 'g2b_import_period_idx');
        });

        Schema::create('gstr2b_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('gstr2b_import_id')->constrained('gstr2b_imports')->cascadeOnDelete();
            $table->string('section', 10); // b2b, b2ba, cdnr, cdnra
            $table->string('document_type', 12); // invoice | credit_note | debit_note
            $table->string('supplier_gstin', 15);
            $table->string('supplier_name')->nullable();
            $table->string('document_number', 50);
            $table->string('normalized_number', 50)->index();
            $table->date('document_date')->nullable();
            $table->string('supplier_period', 6)->nullable();
            $table->date('supplier_filed_on')->nullable();
            $table->string('place_of_supply', 2)->nullable();
            $table->boolean('reverse_charge')->default(false);
            $table->string('itc_available', 1)->nullable(); // Y | N | T (temporary)
            $table->string('itc_reason')->nullable();
            $table->decimal('taxable_value', 15, 2)->default(0);
            $table->decimal('igst', 15, 2)->default(0);
            $table->decimal('cgst', 15, 2)->default(0);
            $table->decimal('sgst', 15, 2)->default(0);
            $table->decimal('cess', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            // matched | mismatch | missing_in_books | note
            $table->string('match_status', 20)->default('missing_in_books');
            $table->unsignedBigInteger('vendor_bill_id')->nullable()->index();
            $table->json('differences')->nullable();
            $table->timestamps();

            $table->index(['gstr2b_import_id', 'match_status'], 'g2b_line_status_idx');
            $table->index(['tenant_id', 'supplier_gstin'], 'g2b_line_gstin_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gstr2b_lines');
        Schema::dropIfExists('gstr2b_imports');
    }
};
