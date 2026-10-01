<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank Reconciliation Statement (BRS) upgrade.
 *
 * - journal_entries.bank_date: the date the bank cleared the entry (Tally's
 *   "Bank Date"). An entry is outstanding on a date D when it is dated on or
 *   before D and has no bank_date, or a bank_date after D — which is what lets
 *   the BRS be recomputed for any statement date, not just the latest one.
 * - bank_statement_matches: one statement line can clear several ledger
 *   entries (one deposit slip covering several receipts). The old single
 *   matched_journal_entry_id column is kept (first entry) for compatibility.
 * - bank_statement_lines.reference / import_hash: cheque or UTR number used
 *   for matching, and a fingerprint that stops the same statement row being
 *   imported twice for the same bank account.
 * - bank_reconciliations: statement period start, the BRS as computed when
 *   the reconciliation was locked, and who reopened it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'bank_date')) {
                $table->date('bank_date')->nullable()->after('is_reconciled');
                $table->index(['tenant_id', 'chart_of_account_id', 'is_reconciled'], 'je_tenant_account_reconciled_idx');
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_statement_lines', 'reference')) {
                $table->string('reference', 100)->nullable()->after('description');
            }
            if (! Schema::hasColumn('bank_statement_lines', 'import_hash')) {
                $table->string('import_hash', 64)->nullable()->after('amount');
                $table->index(['tenant_id', 'import_hash'], 'bsl_tenant_import_hash_idx');
            }
        });

        Schema::table('bank_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_reconciliations', 'statement_from_date')) {
                $table->date('statement_from_date')->nullable()->after('chart_of_account_id');
            }
            if (! Schema::hasColumn('bank_reconciliations', 'book_balance')) {
                $table->decimal('book_balance', 15, 2)->nullable()->after('closing_balance');
            }
            if (! Schema::hasColumn('bank_reconciliations', 'brs_snapshot')) {
                $table->json('brs_snapshot')->nullable()->after('status');
            }
            if (! Schema::hasColumn('bank_reconciliations', 'notes')) {
                $table->text('notes')->nullable()->after('brs_snapshot');
            }
            if (! Schema::hasColumn('bank_reconciliations', 'reopened_by')) {
                $table->foreignId('reopened_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
                $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            }
        });

        if (! Schema::hasTable('bank_statement_matches')) {
            Schema::create('bank_statement_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
                $table->foreignId('bank_statement_line_id')->constrained('bank_statement_lines')->cascadeOnDelete();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                // Signed like the statement line: positive = deposit, negative = withdrawal.
                $table->decimal('amount', 15, 2);
                $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                // A ledger entry clears the bank exactly once.
                $table->unique('journal_entry_id', 'bsm_journal_entry_unique');
                $table->index(['tenant_id', 'bank_reconciliation_id'], 'bsm_tenant_reconciliation_idx');
                $table->index('bank_statement_line_id', 'bsm_line_idx');
            });
        }

        $this->backfillExistingMatches();
    }

    /**
     * Carry matches made before this migration into the pivot and give their
     * ledger entries a bank date, so older reconciliations still show up
     * correctly in the BRS.
     */
    private function backfillExistingMatches(): void
    {
        $now = now();

        DB::table('bank_statement_lines')
            ->where('is_matched', true)
            ->whereNotNull('matched_journal_entry_id')
            ->orderBy('id')
            ->chunkById(500, function ($lines) use ($now) {
                foreach ($lines as $line) {
                    $exists = DB::table('bank_statement_matches')
                        ->where('journal_entry_id', $line->matched_journal_entry_id)
                        ->exists();

                    if (! $exists) {
                        DB::table('bank_statement_matches')->insert([
                            'tenant_id' => $line->tenant_id,
                            'bank_reconciliation_id' => $line->bank_reconciliation_id,
                            'bank_statement_line_id' => $line->id,
                            'journal_entry_id' => $line->matched_journal_entry_id,
                            'amount' => $line->amount,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('journal_entries')
                        ->where('id', $line->matched_journal_entry_id)
                        ->whereNull('bank_date')
                        ->update(['bank_date' => $line->transaction_date]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_matches');

        Schema::table('bank_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('bank_reconciliations', 'reopened_by')) {
                $table->dropConstrainedForeignId('reopened_by');
                $table->dropColumn('reopened_at');
            }
            foreach (['statement_from_date', 'book_balance', 'brs_snapshot', 'notes'] as $column) {
                if (Schema::hasColumn('bank_reconciliations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statement_lines', 'import_hash')) {
                $table->dropIndex('bsl_tenant_import_hash_idx');
                $table->dropColumn('import_hash');
            }
            if (Schema::hasColumn('bank_statement_lines', 'reference')) {
                $table->dropColumn('reference');
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'bank_date')) {
                $table->dropIndex('je_tenant_account_reconciled_idx');
                $table->dropColumn('bank_date');
            }
        });
    }
};
