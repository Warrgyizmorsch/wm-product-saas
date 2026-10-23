<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which ledgers are kept bill-by-bill (party ledgers by default).
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('bill_wise')->default(false)->after('is_cash_or_bank');
        });

        DB::table('chart_of_accounts')
            ->whereIn('system_key', [
                'accounts_receivable', 'accounts_payable', 'creditors_for_expenses',
                'advance_from_customers', 'advance_to_suppliers', 'advance_to_employees', 'salary_payable',
            ])
            ->update(['bill_wise' => true]);

        // Matching On Account / Advance money to bills without a new journal.
        Schema::create('bill_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('party_type', 30);
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('control_account_id');
            $table->date('settled_on');
            $table->decimal('amount', 15, 2);
            $table->string('note', 255)->nullable();
            // Set when an advance on another ledger was moved onto the bill by a journal.
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'party_type', 'party_id']);
        });


        // A voucher waiting for approval keeps the bill allocations chosen on
        // its form until it posts.
        Schema::table('journals', function (Blueprint $table) {
            $table->json('bill_allocation_hints')->nullable();
        });

        // An opening balance broken into the old bills it is made of.
        foreach (['customers', 'vendors'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('opening_bills')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'vendors'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('opening_bills'));
        }
        Schema::table('journals', fn (Blueprint $t) => $t->dropColumn('bill_allocation_hints'));
        Schema::dropIfExists('bill_settlements');
        Schema::table('chart_of_accounts', fn (Blueprint $t) => $t->dropColumn('bill_wise'));
    }
};
