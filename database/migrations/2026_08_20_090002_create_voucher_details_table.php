<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->string('voucher_type'); // payment, receipt, contra, credit_note, debit_note
            $table->string('party_type')->nullable(); // free label, e.g. customer/vendor/employee/other — not a real morph
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('party_name')->nullable(); // free-text fallback when no party record is picked
            $table->string('payment_method')->nullable(); // cash, bank_transfer, cheque, upi, card, other
            $table->string('reference_no')->nullable(); // cheque no / UTR / UPI ref
            $table->timestamps();

            $table->unique('journal_id');
            $table->index(['tenant_id', 'voucher_type']);
            $table->index(['tenant_id', 'party_type', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_details');
    }
};
