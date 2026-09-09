<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Imports\BankStatementLineImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class BankReconciliationService
{
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
