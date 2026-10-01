<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\BankStatementMatch;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Support\VoucherType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pairs bank statement lines with ledger entries on the bank account.
 *
 * - Auto-match: cheque/UTR reference + exact amount first, then exact amount
 *   with the closest date (inside a short window, or a longer one when only
 *   one entry qualifies). Ambiguous lines are left for a person.
 * - Manual match: one line against one or more entries whose total equals the
 *   line, optionally posting a small difference (bank fee, rounding) to a
 *   chosen account.
 * - Adjustment: for bank-only items (charges, interest, direct debits), post a
 *   new journal between the bank and a chosen ledger and match it.
 * - Unmatch: undo any of the above while the reconciliation is open.
 *
 * Matching stamps the entry's bank_date with the statement line's date, which
 * is what the Bank Reconciliation Statement reads.
 */
class BankReconciliationMatcher
{
    /** Exact-amount matches this close in date are taken even if another candidate exists further away. */
    public const NEAR_WINDOW_DAYS = 10;

    /** An exact-amount match up to this far apart is taken only when it is the only candidate. */
    public const FAR_WINDOW_DAYS = 90;

    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly BankReconciliationStatementService $statement,
        private readonly BankReconciliationRuleService $rules,
    ) {
    }

    /**
     * Unreconciled bank entries dated on or before the statement date.
     *
     * @return Collection<int, JournalEntry>
     */
    public function candidates(BankReconciliation $reconciliation): Collection
    {
        return $this->statement
            ->bankLedgerQuery($reconciliation->tenant_id, $reconciliation->chart_of_account_id, $reconciliation->statement_date, [Journal::STATUS_POSTED])
            ->where('is_reconciled', false)
            ->with(['journal' => fn ($q) => $q->withoutGlobalScope('tenant')->with(['voucherDetail' => fn ($q) => $q->withoutGlobalScope('tenant')])])
            ->get()
            ->sortBy(fn (JournalEntry $entry) => [$entry->journal?->journal_date?->timestamp, $entry->id])
            ->values();
    }

    public function autoMatch(BankReconciliation $reconciliation, ?int $matchedBy = null): int
    {
        $this->assertInProgress($reconciliation);

        $candidates = $this->candidates($reconciliation);
        $lines = $reconciliation->statementLines()->unmatched()->orderBy('transaction_date')->orderBy('id')->get();
        $matched = 0;

        // Pass 1 — reference and amount agree. Strongest signal, so it runs
        // across all lines before any amount-only guess can take an entry.
        foreach ($lines as $index => $line) {
            $entry = $candidates->first(fn (JournalEntry $entry) => $this->sameAmount($entry, $line) && $this->referenceMatches($line, $entry));

            if ($entry !== null) {
                $this->attach($reconciliation, $line->id, [$entry->id], $matchedBy, BankStatementMatch::METHOD_REFERENCE);
                $candidates = $candidates->reject(fn ($c) => $c->id === $entry->id)->values();
                $lines->forget($index);
                $matched++;
            }
        }

        // Pass 2 — exact amount, closest date.
        foreach ($lines as $line) {
            $entry = $this->bestAmountMatch($line, $candidates);

            if ($entry !== null) {
                $this->attach($reconciliation, $line->id, [$entry->id], $matchedBy, BankStatementMatch::METHOD_AMOUNT);
                $candidates = $candidates->reject(fn ($c) => $c->id === $entry->id)->values();
                $line->is_matched = true;
                $matched++;
            }
        }

        // Pass 3 — lines nothing in the books explains, where a narration rule
        // marked "post automatically" applies (bank charges, interest, …).
        $autoRules = $this->rules->rulesFor($reconciliation)->where('auto_post', true)->values();
        if ($autoRules->isNotEmpty()) {
            foreach ($this->rules->suggestions($lines->reject(fn ($line) => $line->is_matched), $autoRules) as $lineId => $rule) {
                try {
                    $this->postAdjustment($reconciliation, $lineId, $rule->target_account_id, $rule->narration, $matchedBy, $rule->party_name, null, BankStatementMatch::METHOD_RULE);
                    $this->rules->recordHit($rule);
                    $matched++;
                } catch (InvalidArgumentException $e) {
                    // A rule whose ledger can no longer be posted to is skipped, not fatal.
                    report($e);
                }
            }
        }

        return $matched;
    }

    /**
     * Up to $limit likely entries for each unmatched line, best first: same
     * amount and reference, then same amount by date distance.
     *
     * @param Collection<int, BankStatementLine> $lines
     * @param Collection<int, JournalEntry> $candidates
     * @return array<int, list<int>> statement line id => journal entry ids
     */
    public function suggestions(Collection $lines, Collection $candidates, int $limit = 3): array
    {
        $suggestions = [];

        foreach ($lines as $line) {
            if ($line->is_matched) {
                continue;
            }

            $suggestions[$line->id] = $candidates
                ->filter(fn (JournalEntry $entry) => $this->sameAmount($entry, $line))
                ->sortBy(fn (JournalEntry $entry) => [
                    $this->referenceMatches($line, $entry) ? 0 : 1,
                    $this->daysApart($entry, $line),
                ])
                ->take($limit)
                ->pluck('id')
                ->values()
                ->all();
        }

        return $suggestions;
    }

    /**
     * Match one statement line to one or more ledger entries. When the entries
     * don't add up to the line, $differenceAccountId says where the gap goes
     * (e.g. Bank Charges); without it the match is refused.
     *
     * @param list<int> $journalEntryIds
     */
    public function match(
        BankReconciliation $reconciliation,
        int $statementLineId,
        array $journalEntryIds,
        ?int $differenceAccountId = null,
        ?string $differenceDescription = null,
        ?int $matchedBy = null,
    ): void {
        $this->assertInProgress($reconciliation);

        $journalEntryIds = array_values(array_unique(array_map('intval', $journalEntryIds)));

        if ($journalEntryIds === []) {
            throw new InvalidArgumentException('Select at least one ledger entry to match.');
        }

        DB::transaction(function () use ($reconciliation, $statementLineId, $journalEntryIds, $differenceAccountId, $differenceDescription, $matchedBy) {
            $line = $this->lockUnmatchedLine($reconciliation, $statementLineId);
            $entries = $this->lockCandidateEntries($reconciliation, $journalEntryIds);

            $selectedMinor = $entries->sum(fn (JournalEntry $entry) => (int) round($entry->signedAmount() * 100));
            $lineMinor = (int) round((float) $line->amount * 100);
            $differenceMinor = $lineMinor - $selectedMinor;

            if ($differenceMinor !== 0) {
                if ($differenceAccountId === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Selected entries total %s but the statement line is %s. Choose an account for the difference of %s, or change the selection.',
                        number_format($selectedMinor / 100, 2),
                        number_format($lineMinor / 100, 2),
                        number_format($differenceMinor / 100, 2),
                    ));
                }

                $adjustment = $this->postBankVoucher(
                    $reconciliation,
                    $line,
                    [['account_id' => $differenceAccountId, 'amount' => abs($differenceMinor / 100)]],
                    $differenceMinor / 100,
                    ['memo' => $differenceDescription ?: 'Difference on bank statement line' . ($line->description ? ": {$line->description}" : '')],
                    $matchedBy,
                );

                $entries->push($adjustment);
            }

            $this->recordMatch($reconciliation, $line, $entries, $matchedBy, BankStatementMatch::METHOD_MANUAL);
        });
    }

    /**
     * For a bank-only item (charges, interest, direct debit/credit, a receipt
     * nobody entered): post a voucher dated on the line against one ledger —
     * or several ($splits, e.g. amount + GST) — and match it.
     *
     * @param list<array{account_id: int, amount: float, narration?: ?string}>|null $splits
     */
    public function postAdjustment(
        BankReconciliation $reconciliation,
        int $statementLineId,
        ?int $chartOfAccountId,
        ?string $description = null,
        ?int $postedBy = null,
        ?string $partyName = null,
        ?array $splits = null,
        string $method = BankStatementMatch::METHOD_POSTED,
    ): Journal {
        $this->assertInProgress($reconciliation);

        return DB::transaction(function () use ($reconciliation, $statementLineId, $chartOfAccountId, $description, $postedBy, $partyName, $splits, $method) {
            $line = $this->lockUnmatchedLine($reconciliation, $statementLineId);

            $splits = $splits !== null && $splits !== []
                ? $splits
                : [['account_id' => (int) $chartOfAccountId, 'amount' => abs((float) $line->amount)]];

            $bankEntry = $this->postBankVoucher(
                $reconciliation,
                $line,
                $splits,
                (float) $line->amount,
                ['memo' => $description ?: ($line->description ?: 'Bank statement line'), 'party_name' => $partyName],
                $postedBy,
            );

            $this->recordMatch($reconciliation, $line, collect([$bankEntry]), $postedBy, $method);

            return $bankEntry->journal;
        });
    }

    /**
     * Post many bank lines at once, each as its own voucher. Each line is
     * independent: one bad line is reported and the rest still post.
     *
     * @param array<int, array{account_id: ?int, party_name?: ?string, narration?: ?string}> $items statement line id => choice
     * @return array{posted: int, errors: array<int, string>} errors keyed by statement line id
     */
    public function postMany(BankReconciliation $reconciliation, array $items, ?int $postedBy = null, bool $learn = true): array
    {
        $this->assertInProgress($reconciliation);

        $posted = 0;
        $errors = [];

        foreach ($items as $lineId => $item) {
            if (empty($item['account_id'])) {
                $errors[(int) $lineId] = 'No ledger chosen.';
                continue;
            }

            try {
                $this->postAdjustment(
                    $reconciliation,
                    (int) $lineId,
                    (int) $item['account_id'],
                    $item['narration'] ?? null,
                    $postedBy,
                    $item['party_name'] ?? null,
                );
                $posted++;

                if ($learn) {
                    $line = $reconciliation->statementLines()->find($lineId);
                    $this->rules->learnFrom($reconciliation, $line, (int) $item['account_id'], $item['party_name'] ?? null, $postedBy);
                }
            } catch (InvalidArgumentException $e) {
                $errors[(int) $lineId] = $e->getMessage();
            }
        }

        return ['posted' => $posted, 'errors' => $errors];
    }

    public function unmatch(BankReconciliation $reconciliation, int $statementLineId): void
    {
        $this->assertInProgress($reconciliation);

        DB::transaction(function () use ($reconciliation, $statementLineId) {
            $line = $reconciliation->statementLines()->whereKey($statementLineId)->lockForUpdate()->first();

            if ($line === null) {
                throw new InvalidArgumentException('Statement line not found on this reconciliation.');
            }

            if (! $line->is_matched) {
                throw new InvalidArgumentException('This statement line is not matched.');
            }

            $entryIds = BankStatementMatch::withoutGlobalScope('tenant')
                ->where('bank_statement_line_id', $line->id)
                ->pluck('journal_entry_id')
                ->push($line->matched_journal_entry_id)
                ->filter()
                ->unique()
                ->values();

            JournalEntry::withoutGlobalScope('tenant')
                ->where('tenant_id', $reconciliation->tenant_id)
                ->whereIn('id', $entryIds)
                ->update([
                    'is_reconciled' => false,
                    'bank_date' => null,
                    'reconciled_at' => null,
                    'bank_reconciliation_id' => null,
                ]);

            BankStatementMatch::withoutGlobalScope('tenant')->where('bank_statement_line_id', $line->id)->delete();

            $line->update(['is_matched' => false, 'matched_journal_entry_id' => null]);
        });
    }

    /**
     * Accounts a bank line can be posted against: active, postable (not a
     * group with sub-accounts) and not the bank account itself.
     */
    public function assertPostableAccount(BankReconciliation $reconciliation, int $chartOfAccountId): ChartOfAccount
    {
        $account = ChartOfAccount::withoutGlobalScope('tenant')
            ->where('tenant_id', $reconciliation->tenant_id)
            ->whereKey($chartOfAccountId)
            ->first();

        if ($account === null) {
            throw new InvalidArgumentException('Selected ledger account was not found.');
        }

        if ($account->id === $reconciliation->chart_of_account_id) {
            throw new InvalidArgumentException('Ledger account must be different from the bank account being reconciled.');
        }

        if (! $account->is_active) {
            throw new InvalidArgumentException("Ledger account {$account->code} - {$account->name} is inactive.");
        }

        if (ChartOfAccount::withoutGlobalScope('tenant')->where('parent_id', $account->id)->exists()) {
            throw new InvalidArgumentException("{$account->code} - {$account->name} is a group account. Select a specific ledger under it.");
        }

        return $account;
    }

    /** @param list<int> $journalEntryIds */
    private function attach(BankReconciliation $reconciliation, int $statementLineId, array $journalEntryIds, ?int $matchedBy, string $method): void
    {
        DB::transaction(function () use ($reconciliation, $statementLineId, $journalEntryIds, $matchedBy, $method) {
            $line = $this->lockUnmatchedLine($reconciliation, $statementLineId);
            $entries = $this->lockCandidateEntries($reconciliation, $journalEntryIds);

            $this->recordMatch($reconciliation, $line, $entries, $matchedBy, $method);
        });
    }

    /**
     * Posts Bank ↔ $accountId for a signed bank movement (positive = money in)
     * and returns the new bank-side entry.
     */
    /**
     * Posts a voucher for a signed bank movement (positive = money in) against
     * one or more ledgers, and returns the bank-side entry.
     *
     * Money in → Receipt voucher, money out → Payment voucher, bank ↔ cash or
     * bank ↔ bank → Contra voucher — so these appear in the voucher registers
     * with a number, party, mode and reference like any voucher keyed in by hand.
     *
     * @param list<array{account_id: int, amount: float, narration?: ?string}> $splits positive amounts adding up to |$signedAmount|
     * @param array{party_name?: ?string, memo?: ?string} $meta
     */
    private function postBankVoucher(BankReconciliation $reconciliation, BankStatementLine $line, array $splits, float $signedAmount, array $meta, ?int $postedBy): JournalEntry
    {
        if ($splits === []) {
            throw new InvalidArgumentException('Choose the ledger account to post this bank line to.');
        }

        $totalMinor = (int) round(abs($signedAmount) * 100);
        $splitMinor = array_sum(array_map(fn ($split) => (int) round(abs((float) $split['amount']) * 100), $splits));

        if ($splitMinor !== $totalMinor) {
            throw new InvalidArgumentException(sprintf(
                'The split amounts add up to %s but the bank line is %s.',
                number_format($splitMinor / 100, 2),
                number_format($totalMinor / 100, 2),
            ));
        }

        $memo = $meta['memo'] ?? null;
        $isInflow = $signedAmount > 0;
        $accounts = [];
        $voucherLines = [[
            'chart_of_account_id' => $reconciliation->chart_of_account_id,
            'debit' => $isInflow ? $totalMinor / 100 : 0,
            'credit' => $isInflow ? 0 : $totalMinor / 100,
            'description' => $memo,
        ]];

        foreach ($splits as $split) {
            $account = $this->assertPostableAccount($reconciliation, (int) $split['account_id']);
            $accounts[] = $account;
            $amount = round(abs((float) $split['amount']), 2);

            if ($amount === 0.0) {
                continue;
            }

            $voucherLines[] = [
                'chart_of_account_id' => $account->id,
                'debit' => $isInflow ? 0 : $amount,
                'credit' => $isInflow ? $amount : 0,
                'description' => ($split['narration'] ?? null) ?: $memo,
            ];
        }

        $type = count($accounts) === 1 && $accounts[0]->is_cash_or_bank
            ? VoucherType::CONTRA
            : ($isInflow ? VoucherType::RECEIPT : VoucherType::PAYMENT);

        $journal = $this->vouchers->post($type, $voucherLines, [
            'tenant_id' => $reconciliation->tenant_id,
            'company_id' => $reconciliation->company_id,
            'branch_id' => $reconciliation->branch_id,
            'journal_date' => $line->transaction_date,
            'reference_type' => 'bank_statement_line',
            'reference_id' => $line->id,
            'memo' => $memo,
            'party_name' => ($meta['party_name'] ?? null) ?: null,
            'payment_method' => $this->paymentMethodFor($line),
            'reference_no' => $line->reference,
            'posted_by' => $postedBy,
        ]);

        return $journal->entries()->where('chart_of_account_id', $reconciliation->chart_of_account_id)->firstOrFail();
    }

    /**
     * Payment mode from the bank's narration: NEFT/RTGS/IMPS → bank transfer,
     * UPI → UPI, cheque/clearing → cheque, ATM/cash → cash.
     */
    public function paymentMethodFor(BankStatementLine $line): string
    {
        $text = strtolower(($line->description ?? '') . ' ' . ($line->reference ?? ''));

        return match (true) {
            (bool) preg_match('/\bupi\b/', $text) => 'upi',
            (bool) preg_match('/\b(chq|cheque|clg|clearing|cts|micr)\b/', $text) => 'cheque',
            (bool) preg_match('/\b(atm|cash)\b/', $text) => 'cash',
            (bool) preg_match('/\b(pos|card)\b/', $text) => 'card',
            default => 'bank_transfer',
        };
    }

    /**
     * @param Collection<int, JournalEntry> $entries
     */
    public function recordMatch(BankReconciliation $reconciliation, BankStatementLine $line, Collection $entries, ?int $matchedBy, string $method = BankStatementMatch::METHOD_MANUAL): void
    {
        $bankDate = Carbon::parse($line->transaction_date)->toDateString();

        foreach ($entries as $entry) {
            BankStatementMatch::create([
                'tenant_id' => $reconciliation->tenant_id,
                'bank_reconciliation_id' => $reconciliation->id,
                'bank_statement_line_id' => $line->id,
                'journal_entry_id' => $entry->id,
                'amount' => $entry->signedAmount(),
                'method' => $method,
                'matched_by' => $matchedBy,
            ]);

            $entry->forceFill([
                'is_reconciled' => true,
                'bank_date' => $bankDate,
                'reconciled_at' => now(),
                'bank_reconciliation_id' => $reconciliation->id,
            ])->save();
        }

        $line->update([
            'is_matched' => true,
            'matched_journal_entry_id' => $entries->first()?->id,
        ]);
    }

    public function lockUnmatchedLine(BankReconciliation $reconciliation, int $statementLineId): BankStatementLine
    {
        $line = $reconciliation->statementLines()->whereKey($statementLineId)->lockForUpdate()->first();

        if ($line === null) {
            throw new InvalidArgumentException('Statement line not found on this reconciliation.');
        }

        if ($line->is_matched) {
            throw new InvalidArgumentException('This statement line is already matched. Unmatch it first to change the match.');
        }

        return $line;
    }

    /**
     * @param list<int> $journalEntryIds
     * @return Collection<int, JournalEntry>
     */
    private function lockCandidateEntries(BankReconciliation $reconciliation, array $journalEntryIds): Collection
    {
        $entries = $this->statement
            ->bankLedgerQuery($reconciliation->tenant_id, $reconciliation->chart_of_account_id, $reconciliation->statement_date, [Journal::STATUS_POSTED])
            ->whereIn('id', $journalEntryIds)
            ->lockForUpdate()
            ->get();

        if ($entries->count() !== count($journalEntryIds)) {
            throw new InvalidArgumentException('One or more selected entries are not posted entries on this bank account dated on or before the statement date.');
        }

        if ($entries->contains(fn (JournalEntry $entry) => $entry->is_reconciled)) {
            throw new InvalidArgumentException('One or more selected entries have already been reconciled.');
        }

        return $entries;
    }

    /**
     * @param Collection<int, JournalEntry> $candidates
     */
    private function bestAmountMatch(BankStatementLine $line, Collection $candidates): ?JournalEntry
    {
        $sameAmount = $candidates
            ->filter(fn (JournalEntry $entry) => $this->sameAmount($entry, $line))
            ->map(fn (JournalEntry $entry) => ['entry' => $entry, 'days' => $this->daysApart($entry, $line)])
            ->filter(fn ($row) => $row['days'] <= self::FAR_WINDOW_DAYS)
            ->sortBy('days')
            ->values();

        if ($sameAmount->isEmpty()) {
            return null;
        }

        $best = $sameAmount->first();
        $runnerUp = $sameAmount->get(1);

        if ($best['days'] <= self::NEAR_WINDOW_DAYS) {
            // Two equally close candidates: can't tell which one the bank paid.
            return ($runnerUp !== null && $runnerUp['days'] === $best['days']) ? null : $best['entry'];
        }

        return $runnerUp === null ? $best['entry'] : null;
    }

    private function sameAmount(JournalEntry $entry, BankStatementLine $line): bool
    {
        return (int) round($entry->signedAmount() * 100) === (int) round((float) $line->amount * 100);
    }

    private function daysApart(JournalEntry $entry, BankStatementLine $line): int
    {
        $entryDate = $entry->journal?->journal_date;

        return $entryDate ? (int) abs(Carbon::parse($entryDate)->diffInDays(Carbon::parse($line->transaction_date))) : PHP_INT_MAX;
    }

    /**
     * Cheque/UTR numbers from the statement line (its reference column, or
     * long digit runs in the narration) found in the voucher reference, memo,
     * journal number or entry description.
     */
    public function referenceMatches(BankStatementLine $line, JournalEntry $entry): bool
    {
        $tokens = $this->referenceTokens($line);

        if ($tokens === []) {
            return false;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $entry->journal?->voucherDetail?->reference_no,
            $entry->journal?->memo,
            $entry->journal?->journal_number,
            $entry->description,
        ])));

        if ($haystack === '') {
            return false;
        }

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function referenceTokens(BankStatementLine $line): array
    {
        $tokens = [];

        if ($line->reference) {
            $reference = strtolower(trim($line->reference));
            // Bank exports pad cheque numbers ("000123"); books usually don't.
            foreach ([$reference, ltrim($reference, '0')] as $candidate) {
                if (strlen($candidate) >= 4) {
                    $tokens[] = $candidate;
                }
            }
        }

        if ($line->description && preg_match_all('/[a-z0-9]*\d[a-z0-9]*/i', $line->description, $m)) {
            foreach ($m[0] as $token) {
                // Only long codes (cheque/UTR/UPI refs); short numbers are noise.
                if (strlen($token) >= 6 && preg_match_all('/\d/', $token) >= 5) {
                    $tokens[] = strtolower($token);
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    private function assertInProgress(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->isCompleted()) {
            throw new InvalidArgumentException('This reconciliation is already completed and locked.');
        }
    }
}
