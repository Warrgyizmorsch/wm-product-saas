<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FRS 2.3 / Task C-19: Vendor Master Enhancement.
     * 1. vendor_items: maps internal products to vendor-specific item codes, names, MOQ, lead time, and contracted rates.
     * 2. vendor_item_price_histories: tracks rate-hike logs, effective dates, reasons, and approvals.
     * 3. vendor_documents: tracks statutory and compliance documents (MSME, LDC Form 13, ISO, Agreements) with expiry alerts.
     */
    public function up(): void
    {
        if (! Schema::hasTable('vendor_items')) {
            Schema::create('vendor_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('vendor_item_code')->nullable();
                $table->string('vendor_item_name')->nullable();
                $table->decimal('unit_price', 15, 2)->nullable();
                $table->decimal('moq', 12, 2)->default(1.00);
                $table->unsignedSmallInteger('lead_time_days')->default(0);
                $table->boolean('is_preferred')->default(false);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'vendor_id', 'product_id'], 'vendor_items_tenant_vendor_product_unique');
            });
        }

        if (! Schema::hasTable('vendor_item_price_histories')) {
            Schema::create('vendor_item_price_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('vendor_item_id')->index();
                $table->decimal('old_price', 15, 2)->nullable();
                $table->decimal('new_price', 15, 2);
                $table->date('effective_from');
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('vendor_item_id')
                    ->references('id')
                    ->on('vendor_items')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('vendor_documents')) {
            Schema::create('vendor_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->string('document_type', 50)->default('other'); // msme, ldc_form13, iso_cert, gst_cert, agreement, nda, other
                $table->string('document_name');
                $table->string('document_number')->nullable();
                $table->string('file_path')->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->json('reminder_days')->nullable(); // e.g. [30, 15, 7]
                $table->string('status', 30)->default('valid'); // valid, expiring_soon, expired
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'vendor_id', 'expiry_date'], 'vendor_docs_tenant_vendor_expiry_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
        Schema::dropIfExists('vendor_item_price_histories');
        Schema::dropIfExists('vendor_items');
    }
};
