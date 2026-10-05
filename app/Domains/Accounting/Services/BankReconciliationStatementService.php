<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the Bank Reconciliation Statement (BRS) for a reconciliation, in the
 * format Tally and most Indian auditors use:
 *
 *     Balance as per company books (on the statement date)
 *   + Cheques issued / payments not yet presented to the bank
 *   − Cheques deposited / receipts not yet credited by the bank
 *   + Amounts credited by the bank but not yet in the books
 *   − Amounts debited by the bank but not yet in the books
 *   = Balance as per bank (computed)
 *     … which must equal the closing balance on the bank statement.
 *
 * "Not yet presented / credited" is worked out from journal_entries.bank_date
 * (the date the bank cleared the entry), so the statement is correct for any
 * statement date, including after later reconciliations have cleared more.
 * Bank accounts are debit-normal: a deposit is a debit, a payment a credit.
 */
class BankReconciliationStatementService
{
    /** Opening-balance journals are not bank transactions and never appear on a statement. */
    public const OPENING_BALANCE_REFERENCE = 'chart_of_account_opening_balance';

    /**
     * @return array{
     *     account: ChartOfAccount,
     *     statement_date: Carbon,
     *     book_balance: float,
     *     cheques_not_presented: Collection<int, array<string, mixed>>,
     *     cheques_not_presented_total: float,
     *     deposits_not_credited: Collection<int, array<string, mixed>>,
     *     deposits_not_credited_total: float,
     *     bank_credits_not_in_books: Collection<int, array<string, mixed>>,
     *     bank_credits_not_in_books_total: float,
     *     bank_debits_not_in_books: Collection<int, array<string, mixed>>,
     *     bank_debits_not_in_books_total: float,
     *     computed_bank_balance: float,
     *     statement_balance: float,
     *     difference: float,
     *     statement_check: array{opening: float, lines_total: float, expected_closing: float, closing: float, difference: float},
     *     opening_check: array{previous_closing: ?float, previous_date: ?string, opening: float, difference: ?float},
     *     line_counts: array{total: int, matched: int, unmatched: int},
     * }
     */
    public function build(BankReconciliation $reconciliation): array
    {
        $reconciliation->loadMissing('chartOfAccount');
        $date = Carbon::parse($reconciliation->statement_date)->startOfDay();

        $bookBalance = $this->bookBalance($reconciliation->tenant_id, $reconciliation->chart_of_account_id, $date);

        $outstanding = $this->outstandingEntries($reconciliation->tenant_id, $reconciliation->chart_of_account_id, $date)
            ->map(fn (JournalEntry $entry) => [
                'journal_entry_id' => $entry->id,
                'journal_id' => $entry->journal_id,
                'journal_number' => $entry->journal?->journal_number,
                'date' => $entry->journal?->journal_date?->toDateString(),
                'description' => $entry->description ?: $entry->journal?->memo,
                'reference' => $entry->journal?->voucherDetail?->reference_no,
                'amount' => round(abs($entry->signedAmount()), 2),
                'signed' => $entry->signedAmount(),
            ]);

        $chequesNotPresented = $outstanding->filter(fn ($row) => $row['signed'] < 0)->values();
        $depositsNotCredited = $outstanding->filter(fn ($row) => $row['signed'] > 0)->values();

        $lines = $reconciliation->statementLines()->orderBy('transaction_date')->orderBy('id')->get();
        $unmatched = $lines->where('is_matched', false)->map(fn ($line) => [
            'statement_line_id' => $line->id,
            'date' => $line->transaction_date?->toDateString(),
            'description' => $line->description,
            'reference' => $line->reference,
            'amount' => round(abs((float) $line->amount), 2),
            'signed' => round((float) $line->amount, 2),
        ]);

        $bankCredits = $unmatched->filter(fn ($row) => $row['signed'] > 0)->values();
        $bankDebits = $unmatched->filter(fn ($row) => $row['signed'] < 0)->values();

        $chequesTotal = $this->sum($chequesNotPresented);
        $depositsTotal = $this->sum($depositsNotCredited);
        $creditsTotal = $this->sum($bankCredits);
        $debitsTotal = $this->sum($bankDebits);

        $computedBank = round($bookBalance + $chequesTotal - $depositsTotal + $creditsTotal - $debitsTotal, 2);
        $statementBalance = round((float) $reconciliation->closing_balance, 2);

        $linesTotal = round((float) $lines->sum('amount'), 2);
        $expectedClosing = round((float) $reconciliation->opening_balance + $linesTotal, 2);

        $previous = $this->previousCompleted($reconciliation);

        return [
            'account' => $reconciliation->chartOfAccount,
            'statement_date' => $date,
            'book_balance' => $bookBalance,
            'cheques_not_presented' => $chequesNotPresented,
            'cheques_not_presented_total' => $chequesTotal,
            'deposits_not_credited' => $depositsNotCredited,
            'deposits_not_credited_total' => $depositsTotal,
            'bank_credits_not_in_books' => $bankCredits,
            'bank_credits_not_in_books_total' => $creditsTotal,
            'bank_debits_not_in_books' => $bankDebits,
            'bank_debits_not_in_books_total' => $debitsTotal,
            'computed_bank_balance' => $computedBank,
            'statement_balance' => $statementBalance,
            'difference' => round($statementBalance - $computedBank, 2),
            'statement_check' => [
                'opening' => round((float) $reconciliation->opening_balance, 2),
                'lines_total' => $linesTotal,
                'expected_closing' => $expectedClosing,
                'closing' => $statementBalance,
                'difference' => round($statementBalance - $expectedClosing, 2),
            ],
            'opening_check' => [
                'previous_closing' => $previous ? round((float) $previous->closing_balance, 2) : null,
                'previous_date' => $previous?->statement_date?->toDateString(),
                'opening' => round((float) $reconciliation->opening_balance, 2),
                'difference' => $previous ? round((float) $reconciliation->opening_balance - (float) $previous->closing_balance, 2) : null,
            ],
            'line_counts' => [
                'total' => $lines->count(),
                'matched' => $lines->where('is_matched', true)->count(),
                'unmatched' => $unmatched->count(),
            ],
        ];
    }

    /**
     * Signed (debit − credit) balance of the account on $date, including the
     * ledger's opening balance journal — exactly what the Bank Book shows.
     */
    public function bookBalance(int $tenantId, int $accountId, \DateTimeInterface $date): float
    {
        $totals = JournalEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $accountId)
            ->whereHas('journal', fn ($q) => $q->withoutGlobalScope('tenant')
                ->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
                ->whereDate('journal_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        return round((float) $totals->debit - (float) $totals->credit, 2);
    }

    /**
     * Book entries on the bank account dated on or before $date that the
     * bank had not cleared by $date. A journal and its reversal both dated by
     * $date cancel out and are left off; opening balances are never listed.
     *
     * @return Collection<int, JournalEntry>
     */
    public function outstandingEntries(int $tenantId, int $accountId, \DateTimeInterface $date): Collection
    {
        return $this->bankLedgerQuery($tenantId, $accountId, $date, [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
            ->where(function (Builder $q) use ($date) {
                $q->where(fn (Builder $q) => $q->whereNull('bank_date')->where('is_reconciled', false))
                    ->orWhereDate('bank_date', '>', $date);
            })
            ->with(['journal' => fn ($q) => $q->withoutGlobalScope('tenant')->with(['voucherDetail' => fn ($q) => $q->withoutGlobalScope('tenant')])])
            ->get()
            ->sortBy(fn (JournalEntry $entry) => [$entry->journal?->journal_date?->timestamp, $entry->id])
            ->values();
    }

    /**
     * Entries on a bank account up to $date that are real bank movements:
     * excludes opening-balance journals, reversed journals whose reversal is
     * also on or before $date, and those reversals themselves.
     *
     * @param list<string> $statuses
     */
    public function bankLedgerQuery(int $tenantId, int $accountId, \DateTimeInterface $date, array $statuses): Builder
    {
        return JournalEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $accountId)
            ->whereHas('journal', function (Builder $q) use ($date, $statuses) {
                $q->withoutGlobalScope('tenant')
                    ->whereIn('status', $statuses)
                    ->whereDate('journal_date', '<=', $date)
                    ->where(fn (Builder $q) => $q->whereNull('reference_type')->orWhere('reference_type', '!=', self::OPENING_BALANCE_REFERENCE))
                    // Reversed on or before the date: the pair nets to nothing.
                    ->where(fn (Builder $q) => $q->where('status', '!=', Journal::STATUS_REVERSED)
                        ->orWhereDoesntHave('reversedJournal', fn ($q) => $q->withoutGlobalScope('tenant')->whereDate('journal_date', '<=', $date)))
                    // A reversal journal: its original is always dated on or before it.
                    ->whereDoesntHave('reversalOf', fn ($q) => $q->withoutGlobalScope('tenant'));
            });
    }

    public function previousCompleted(BankReconciliation $reconciliation): ?BankReconciliation
    {
        return BankReconciliation::withoutGlobalScope('tenant')
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('chart_of_account_id', $reconciliation->chart_of_account_id)
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->whereDate('statement_date', '<', $reconciliation->statement_date)
            ->when($reconciliation->exists, fn ($q) => $q->whereKeyNot($reconciliation->id))
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param Collection<int, array{amount: float}> $rows
     */
    private function sum(Collection $rows): float
    {
        // Minor units, so many small amounts can't drift.
        return round($rows->sum(fn ($row) => (int) round($row['amount'] * 100)) / 100, 2);
    }
}
