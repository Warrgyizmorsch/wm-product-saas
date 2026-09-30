<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\BankStatementUpload;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\StatementExtraction\StatementExtractionException;
use App\Domains\Accounting\Services\StatementExtraction\StatementExtractionProvider;
use App\Imports\BankStatementLineImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class BankReconciliationService
{
    public function __construct(
        private readonly StatementExtractionProvider $extractor,
        private readonly JournalService $journals,
    ) {
    }
    /**
     * @param array{tenant_id?: int, company_id?: int, branch_id?: int, chart_of_account_id: int, statement_date: string, opening_balance?: float, closing_balance?: float} $data
     */
    public function start(array $data): BankReconciliation
    {
        $tenantId = $data['tenant_id'] ?? tenant_id();

        $account = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $data['chart_of_account_id'])
            ->first();

        if ($account === null || !$account->is_cash_or_bank) {
            throw new InvalidArgumentException('Reconciliation can only be started against a cash or bank account.');
        }

        return BankReconciliation::create([
            'tenant_id' => $tenantId,
            'company_id' => $data['company_id'] ?? company_id(),
            'branch_id' => $data['branch_id'] ?? branch_id(),
            'chart_of_account_id' => $account->id,
            'statement_date' => $data['statement_date'],
            'opening_balance' => $data['opening_balance'] ?? 0,
            'closing_balance' => $data['closing_balance'] ?? 0,
            'status' => BankReconciliation::STATUS_IN_PROGRESS,
        ]);
    }

    public function importStatementLines(BankReconciliation $reconciliation, UploadedFile $file): int
    {
        $this->assertInProgress($reconciliation);

        $import = new BankStatementLineImport($reconciliation->id, $reconciliation->tenant_id);
        Excel::import($import, $file);

        return $import->importedCount();
    }

    /**
     * Stores the uploaded statement file, sends it to the configured
     * StatementExtractionProvider, and turns the returned rows into
     * BankStatementLine records tagged to the resulting upload. Unlike
     * importStatementLines() (CSV/XLSX, parsed in-memory), the raw file is
     * kept on a private disk — useful for re-extraction/audit — since PDFs
     * and scans can't be losslessly re-derived from the parsed rows alone.
     */
    public function extractStatementLines(BankReconciliation $reconciliation, UploadedFile $file, ?int $extractedBy = null): BankStatementUpload
    {
        $this->assertInProgress($reconciliation);

        $path = $file->store(
            "bank-statements/tenant_{$reconciliation->tenant_id}/reconciliation_{$reconciliation->id}",
            'local'
        );

        $upload = BankStatementUpload::create([
            'tenant_id' => $reconciliation->tenant_id,
            'company_id' => $reconciliation->company_id,
            'branch_id' => $reconciliation->branch_id,
            'bank_reconciliation_id' => $reconciliation->id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'status' => BankStatementUpload::STATUS_PENDING,
            'provider' => class_basename($this->extractor),
        ]);

        try {
            // Not storage_path("app/{$path}") — Laravel's default 'local' disk
            // root is storage/app/private (since Laravel 11), not storage/app;
            // resolving through the disk itself keeps this correct regardless
            // of how that disk is configured.
            $result = $this->extractor->extract(Storage::disk('local')->path($path), $file->getMimeType());
            $rows = $result['lines'] ?? [];

            $lines = DB::transaction(function () use ($reconciliation, $upload, $rows) {
                $created = [];

                foreach ($rows as $row) {
                    $created[] = BankStatementLine::create([
                        'tenant_id' => $reconciliation->tenant_id,
                        'bank_reconciliation_id' => $reconciliation->id,
                        'bank_statement_upload_id' => $upload->id,
                        'transaction_date' => $row['date'],
                        'description' => $row['description'] ?? null,
                        'suggested_ledger' => $row['suggested_ledger'] ?? null,
                        'amount' => $row['amount'],
                    ]);
                }

                return $created;
            });

            $upload->update([
                'status' => BankStatementUpload::STATUS_COMPLETED,
                'extracted_count' => count($lines),
                'raw_response' => $result['raw'] ?? $result,
                'extracted_by' => $extractedBy,
                'extracted_at' => now(),
            ]);

            // Only pre-fill from the statement's own read of its balances when
            // the reconciliation is still at its untouched default (0/0) —
            // never silently overwrite a value the user deliberately entered
            // at "start reconciliation" time.
            if ((float) $reconciliation->opening_balance === 0.0 && (float) $reconciliation->closing_balance === 0.0) {
                $update = array_filter([
                    'opening_balance' => $result['opening_balance'] ?? null,
                    'closing_balance' => $result['closing_balance'] ?? null,
                ], fn ($value) => $value !== null);

                if ($update !== []) {
                    $reconciliation->update($update);
                }
            }
        } catch (StatementExtractionException $e) {
            $upload->update([
                'status' => BankStatementUpload::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }

        return $upload->fresh('statementLines');
    }

    /**
     * Matches unmatched statement lines to unreconciled journal entries on the
     * same cash/bank account by exact signed amount, preferring the closest
     * transaction date. Deliberately conservative (exact amount match only)
     * to avoid mis-matching two different transactions of similar size.
     */
    public function autoMatch(BankReconciliation $reconciliation): int
    {
        $this->assertInProgress($reconciliation);

        $matched = 0;

        $candidates = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('chart_of_account_id', $reconciliation->chart_of_account_id)
            ->where('is_reconciled', false)
            ->whereHas('journal', fn ($query) => $query->where('status', 'posted'))
            ->with('journal')
            ->get();

        foreach ($reconciliation->statementLines()->unmatched()->get() as $line) {
            $lineAmount = round((float) $line->amount, 2);

            $entry = $candidates
                ->filter(fn (JournalEntry $entry) => $entry->signedAmount() === $lineAmount)
                ->sortBy(fn (JournalEntry $entry) => abs($entry->journal->journal_date->diffInDays($line->transaction_date)))
                ->first();

            if ($entry === null) {
                continue;
            }

            $this->matchPair($reconciliation, $line, $entry);
            $candidates = $candidates->reject(fn (JournalEntry $candidate) => $candidate->is($entry))->values();
            $matched++;
        }

        return $matched;
    }

    public function manualMatch(BankReconciliation $reconciliation, int $statementLineId, int $journalEntryId): void
    {
        $this->assertInProgress($reconciliation);

        $line = $reconciliation->statementLines()->unmatched()->findOrFail($statementLineId);

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('chart_of_account_id', $reconciliation->chart_of_account_id)
            ->where('is_reconciled', false)
            ->findOrFail($journalEntryId);

        $this->matchPair($reconciliation, $line, $entry);
    }

    /**
     * For a line with no existing journal entry to match against (the normal
     * case for a freshly-imported statement of books that haven't been
     * entered yet) — posts a brand-new balanced journal between the
     * reconciliation's bank account and the chosen ledger, dated to the
     * line's transaction date, then matches it immediately. $chartOfAccountId
     * is normally the caller's confirmation of resolveLedgerByName()'s guess,
     * not a fresh choice — the UI defaults the picker to that suggestion.
     */
    public function createAndMatch(BankReconciliation $reconciliation, int $statementLineId, int $chartOfAccountId, ?int $postedBy = null): void
    {
        $this->assertInProgress($reconciliation);

        $line = $reconciliation->statementLines()->unmatched()->findOrFail($statementLineId);

        $targetAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('id', $chartOfAccountId)
            ->first();

        if ($targetAccount === null) {
            throw new InvalidArgumentException('Selected ledger account was not found.');
        }

        if ($targetAccount->id === $reconciliation->chart_of_account_id) {
            throw new InvalidArgumentException('Ledger account must be different from the bank account being reconciled.');
        }

        $amount = round(abs((float) $line->amount), 2);
        $isInflow = (float) $line->amount > 0;

        $lines = $isInflow
            ? [
                ['chart_of_account_id' => $reconciliation->chart_of_account_id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $targetAccount->id, 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['chart_of_account_id' => $targetAccount->id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $reconciliation->chart_of_account_id, 'debit' => 0, 'credit' => $amount],
            ];

        $journal = $this->journals->post($lines, [
            'tenant_id' => $reconciliation->tenant_id,
            'company_id' => $reconciliation->company_id,
            'branch_id' => $reconciliation->branch_id,
            'journal_date' => $line->transaction_date,
            'source' => Journal::SOURCE_MANUAL,
            'reference_type' => 'bank_statement_line',
            'reference_id' => $line->id,
            'memo' => 'Bank reconciliation: ' . ($line->description ?: $targetAccount->name),
            'posted_by' => $postedBy,
        ]);

        $bankEntry = $journal->entries->firstWhere('chart_of_account_id', $reconciliation->chart_of_account_id);

        $this->matchPair($reconciliation, $line, $bankEntry);
    }

    /**
     * Best-effort match of a statement line's provider-suggested ledger name
     * (e.g. "Paytm (Cr)", "Cash-in-Hand") to a real Chart of Account, for
     * defaulting the "Create & Match" picker — never authoritative, the user
     * confirms or changes it before anything posts.
     */
    public function resolveLedgerByName(int $tenantId, ?string $name): ?ChartOfAccount
    {
        if (empty($name)) {
            return null;
        }

        $clean = trim(preg_replace('/\s*\((Dr|Cr)\)\s*$/i', '', $name));

        if ($clean === '') {
            return null;
        }

        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($clean) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($clean)])
                  ->orWhere('name', 'like', "%{$clean}%");
            })
            ->orderByRaw('LOWER(name) = ? DESC', [strtolower($clean)])
            ->first();
    }

    /**
     * Statement closing balance must reconcile against the opening balance
     * plus every statement line before the reconciliation can be locked —
     * every line must also be matched to a ledger entry, otherwise the
     * "cleared" balance isn't actually verified against the books.
     */
    public function complete(BankReconciliation $reconciliation, int $completedBy): BankReconciliation
    {
        $this->assertInProgress($reconciliation);

        if ($reconciliation->statementLines()->unmatched()->exists()) {
            throw new InvalidArgumentException('All statement lines must be matched before completing the reconciliation.');
        }

        $expectedClosing = round(
            (float) $reconciliation->opening_balance + (float) $reconciliation->statementLines()->sum('amount'),
            2
        );

        if ($expectedClosing !== round((float) $reconciliation->closing_balance, 2)) {
            throw new InvalidArgumentException(
                "Statement does not balance: opening balance plus statement lines totals {$expectedClosing}, expected closing balance {$reconciliation->closing_balance}."
            );
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_COMPLETED,
            'completed_by' => $completedBy,
            'completed_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    private function matchPair(BankReconciliation $reconciliation, BankStatementLine $line, JournalEntry $entry): void
    {
        DB::transaction(function () use ($reconciliation, $line, $entry) {
            $line->update([
                'is_matched' => true,
                'matched_journal_entry_id' => $entry->id,
            ]);

            $entry->update([
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'bank_reconciliation_id' => $reconciliation->id,
            ]);
        });
    }

    private function assertInProgress(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->isCompleted()) {
            throw new InvalidArgumentException('This reconciliation is already completed and locked.');
        }
    }
}
