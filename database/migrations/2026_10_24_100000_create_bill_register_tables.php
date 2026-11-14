<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bill-wise register (Tally's New Ref / Agst Ref / On Account): every
        // Receivable / Payable line with a party is explained by allocations,
        // so a party's allocations always sum to its ledger balance.
        Schema::create('bill_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('party_type', 30);
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('control_account_id');
            $table->string('ref_number', 100);
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'party_type', 'party_id', 'control_account_id', 'ref_number'], 'bill_references_party_ref_unique');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('bill_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->unsignedBigInteger('bill_reference_id')->nullable()->index();
            $table->string('party_type', 30);
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('control_account_id');
            $table->string('ref_type', 20); // new_ref, against_ref, advance, on_account
            $table->decimal('amount', 15, 2); // signed: + debit, - credit
            $table->date('allocated_on');
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'party_type', 'party_id', 'control_account_id'], 'bill_allocations_party_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_allocations');
        Schema::dropIfExists('bill_references');
    }
};
