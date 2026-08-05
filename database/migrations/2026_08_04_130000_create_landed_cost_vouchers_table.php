<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('landed_cost_vouchers')) {
            Schema::create('landed_cost_vouchers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('voucher_number')->unique();
                $table->date('voucher_date');
                $table->string('status')->default('Draft'); // Draft, Posted, Cancelled
                $table->date('posting_date')->nullable();
                $table->decimal('total_expenses', 15, 4)->default(0.0000);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('landed_cost_receipts')) {
            Schema::create('landed_cost_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('landed_cost_voucher_id')->constrained('landed_cost_vouchers')->onDelete('cascade');
                $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('landed_cost_expenses')) {
            Schema::create('landed_cost_expenses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('landed_cost_voucher_id')->constrained('landed_cost_vouchers')->onDelete('cascade');
                $table->string('cost_head'); // Freight, Custom Duty, Loading, Insurance, Other
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->decimal('amount', 15, 4)->default(0.0000);
                $table->string('allocation_basis')->default('by_qty'); // by_qty, by_amount, equal
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('landed_cost_items')) {
            Schema::create('landed_cost_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('landed_cost_voucher_id')->constrained('landed_cost_vouchers')->onDelete('cascade');
                $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes')->onDelete('cascade');
                $table->foreignId('goods_receipt_note_item_id')->constrained('goods_receipt_note_items')->onDelete('cascade');
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
                $table->decimal('quantity', 15, 4)->default(0.0000);
                $table->decimal('base_unit_rate', 15, 4)->default(0.0000);
                $table->decimal('base_total_amount', 15, 4)->default(0.0000);
                $table->decimal('allocated_cost', 15, 4)->default(0.0000);
                $table->decimal('new_landed_unit_cost', 15, 4)->default(0.0000);
                $table->decimal('new_total_amount', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_items');
        Schema::dropIfExists('landed_cost_expenses');
        Schema::dropIfExists('landed_cost_receipts');
        Schema::dropIfExists('landed_cost_vouchers');
    }
};
