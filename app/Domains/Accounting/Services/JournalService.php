<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
        private readonly FiscalPeriodService $periods,
        private readonly AccountingAuditLogService $auditLog,
        private readonly JournalApprovalSettings $approvalSettings,
        private readonly AccessService $access,
    ) {
    }

    /**
     * Entry point for journals and vouchers a person keys in on the Journal or
     * Voucher screens. Posts straight away unless the tenant's maker-checker
     * settings say this one needs a second person, in which case it is saved
     * as pending_approval and stays out of the ledger until approve().
     *
     * Automatic postings (sales, purchase, payments, assets, bank
     * reconciliation) keep calling post() directly — they are not manual
     * entries and must not stall waiting for a human.
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $meta  same keys as post()
     */
    public function submit(array $lines, array $meta = []): Journal
    {
        if (!$this->requiresApproval($lines, $meta)) {
            return $this->post($lines, $meta);
        }

        return DB::transaction(function () use ($lines, $meta) {
            $journal = $this->createJournal($lines, $meta, Journal::STATUS_PENDING_APPROVAL);

            $this->auditLog->record(
                $journal,
                'journal.submitted',
                "Journal {$journal->journal_number} submitted for approval",
                ['total_debit' => $journal->total_debit, 'voucher_type' => $journal->voucher_type]
            );

            return $journal;
        });
    }

    /**
     * Would submit() hold these lines for approval? Exposed so screens can
     * tell the maker before they save.
     */
    public function requiresApproval(array $lines, array $meta = []): bool
    {
        $tenantId = $meta['tenant_id'] ?? tenant_id();
        $settings = $this->approvalSettings->for($tenantId);

        if (!$settings['enabled']) {
            return false;
        }

        $total = round(array_sum(array_map(fn ($line) => (float) ($line['debit'] ?? 0), $lines)), 2);
        if ($total < $settings['threshold']) {
            return false;
        }

        if ($settings['approvers_post_directly'] && !empty($meta['posted_by'])) {
            $maker = User::query()->withoutGlobalScope('tenant')->find($meta['posted_by']);
            if ($maker !== null && $this->access->allows($maker, 'accounting.journals.approve', ['tenant_id' => $tenantId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checker approves a pending journal: it is posted into the period open
     * for its date *now* (the period may have closed while it waited).
     * The maker can never approve their own entry.
     */
    public function approve(int $journalId, int $approverId): Journal
    {
        return DB::transaction(function () use ($journalId, $approverId) {
            $journal = $this->lockPending($journalId);

            if ((int) $journal->posted_by === $approverId) {
                throw new InvalidArgumentException('You entered this journal, so someone else has to approve it.');
            }

            $period = $this->periods->assertOpenPeriodForDate($journal->journal_date);

            $journal->update([
                'status' => Journal::STATUS_POSTED,
                'accounting_period_id' => $period->id,
                'approved_by' => $approverId,
                'approved_at' => now(),
                'posted_at' => now(),
            ]);

            $this->auditLog->record(
                $journal,
                'journal.approved',
                "Journal {$journal->journal_number} approved and posted",
                ['total_debit' => $journal->total_debit, 'maker_id' => $journal->posted_by, 'approver_id' => $approverId]
            );

            return $journal->fresh(['entries']);
        });
    }

    /** Checker sends a pending journal back; it never posts. A reason is required. */
    public function reject(int $journalId, int $userId, string $reason): Journal
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason for rejecting this journal.');
        }

        return DB::transaction(function () use ($journalId, $userId, $reason) {
            $journal = $this->lockPending($journalId);

            $journal->update([
                'status' => Journal::STATUS_REJECTED,
                'rejected_by' => $userId,
                'rejected_at' => now(),
                'rejection_reason' => mb_substr($reason, 0, 500),
            ]);

            $this->auditLog->record(
                $journal,
                'journal.rejected',
                "Journal {$journal->journal_number} rejected",
                ['reason' => $reason, 'maker_id' => $journal->posted_by, 'rejected_by' => $userId]
            );

            return $journal->fresh();
        });
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator */
    public function pendingApproval(int $perPage = 20)
    {
        return Journal::query()
            ->pendingApproval()
            ->with(['entries.account', 'postedBy', 'voucherDetail'])
            ->orderBy('journal_date')
            ->orderBy('id')
            ->paginate($perPage);
    }

    private function lockPending(int $journalId): Journal
    {
        $journal = Journal::query()->whereKey($journalId)->lockForUpdate()->first();

        if ($journal === null) {
            throw new InvalidArgumentException('Journal not found.');
        }

        if (!$journal->isPendingApproval()) {
            throw new InvalidArgumentException("Journal {$journal->journal_number} is {$journal->status}, not waiting for approval.");
        }

        return $journal;
    }

    /**
     * Post a balanced double-entry journal.
     *
     * @param array<int, array{chart_of_account_id: int, debit?: float, credit?: float, description?: string}> $lines
     * @param array{
     *     tenant_id?: int,
     *     company_id?: int,
     *     branch_id?: int,
     *     journal_date?: string|\DateTimeInterface,
     *     source?: string,
     *     voucher_type?: string,
     *     journal_number_prefix?: string,
     *     reference_type?: string,
     *     reference_id?: int,
     *     memo?: string,
     *     posted_by?: int,
     * } $meta
     */
    public function post(array $lines, array $meta = []): Journal
    {
        return $this->postJournal($lines, $meta);
    }

    /**
     * Post the journal for a source document (invoice, bill, payment, payroll
     * run...) exactly once. If the document already has a standing journal, that
     * one is returned and nothing is posted. Once that journal is reversed, the
     * document can be posted again.
     *
     * The check alone can't stop two queue workers posting the same document
     * at the same moment, so the journal also carries an idempotency key with a
     * unique index; whoever loses that race gets the winner's journal back.
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $meta  same keys as post(); reference_type and reference_id are required
     */
    public function postOnce(array $lines, array $meta): Journal
    {
        $referenceType = $meta['reference_type'] ?? null;
        $referenceId = $meta['reference_id'] ?? null;

        if (empty($referenceType) || empty($referenceId)) {
            throw new InvalidArgumentException('postOnce() needs a reference_type and reference_id to know what was posted.');
        }

        $tenantId = (int) ($meta['tenant_id'] ?? require_tenant_id());
        $meta['tenant_id'] = $tenantId;

        $existing = $this->activePosting($tenantId, $referenceType, (int) $referenceId);
        if ($existing !== null) {
            return $existing;
        }

        $meta['idempotency_key'] = self::idempotencyKey($referenceType, (int) $referenceId);

        try {
            return $this->postJournal($lines, $meta);
        } catch (UniqueConstraintViolationException $e) {
            // Lost the race: the other worker's journal is the posting. (A
            // clash on journal_number instead finds nothing and rethrows.)
            return $this->activePosting($tenantId, $referenceType, (int) $referenceId) ?? throw $e;
        }
    }

    /**
     * The journal currently standing as the posting of a source document, or
     * null if it was never posted or its posting was reversed.
     */
    public function activePosting(int $tenantId, string $referenceType, int $referenceId): ?Journal
    {
        return $this->journals->activeForReference($tenantId, $referenceType, $referenceId);
    }

    public static function idempotencyKey(string $referenceType, int $referenceId): string
    {
        return "{$referenceType}:{$referenceId}";
    }

    /**
     * Post the year-end closing journal into the fiscal year's last period,
     * even if that period is already closed: closing entries belong to the
     * year being closed. Only YearEndClosingService calls this.
     */
    public function postClosingEntry(array $lines, array $meta, AccountingPeriod $period): Journal
    {
        $meta['source'] = Journal::SOURCE_YEAR_END_CLOSE;
        $meta['idempotency_key'] = self::idempotencyKey($meta['reference_type'], (int) $meta['reference_id']);

        return $this->postJournal($lines, $meta, $period);
    }

    /**
     * Reverse a year-end closing journal on its own date and period (not today),
     * so reopening a year puts its profit back into that year.
     */
    public function reverseClosingEntry(int $journalId, AccountingPeriod $period, ?int $postedBy, string $reason): Journal
    {
        return $this->reverseJournal($journalId, $reason, $postedBy, $period);
    }

    private function postJournal(array $lines, array $meta, ?AccountingPeriod $period = null): Journal
    {
        return DB::transaction(function () use ($lines, $meta, $period) {
            $journal = $this->createJournal($lines, $meta, Journal::STATUS_POSTED, $period);

            $this->auditLog->record(
                $journal,
                'journal.posted',
                "Journal {$journal->journal_number} posted",
                [
                    'total_debit' => $journal->total_debit,
                    'total_credit' => $journal->total_credit,
                    'source' => $journal->source,
                    'voucher_type' => $journal->voucher_type,
                ]
            );

            return $journal;
        });
    }

    /**
     * Validate and save a journal with its lines in the given status. Balance
     * and open-period checks run for pending journals too, so a maker learns
     * about a closed period immediately rather than at approval time.
     */
    private function createJournal(array $lines, array $meta, string $status, ?AccountingPeriod $period = null): Journal
    {
        $tenantId = $meta['tenant_id'] ?? tenant_id();
        $companyId = $meta['company_id'] ?? company_id();
        $branchId = $meta['branch_id'] ?? branch_id();
        $journalDate = Carbon::parse($meta['journal_date'] ?? now());

        $this->assertLinesAreBalanced($lines);

        if ($period !== null && !$journalDate->betweenIncluded(
            Carbon::parse($period->start_date)->startOfDay(),
            Carbon::parse($period->end_date)->endOfDay(),
        )) {
            throw new InvalidArgumentException("Journal date {$journalDate->toDateString()} is outside period '{$period->name}'.");
        }

        return DB::transaction(function () use ($lines, $meta, $tenantId, $companyId, $branchId, $journalDate, $status, $period) {
            $period ??= $this->periods->assertOpenPeriodForDate($journalDate);

            $totalDebit = array_sum(array_column($lines, 'debit'));
            $totalCredit = array_sum(array_column($lines, 'credit'));

            $journalNumber = $this->journals->nextJournalNumber($tenantId, $meta['journal_number_prefix'] ?? 'JNL');

            $journal = $this->journals->createWithEntries([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'accounting_period_id' => $period->id,
                'journal_number' => $journalNumber,
                'journal_date' => $journalDate,
                'source' => $meta['source'] ?? Journal::SOURCE_MANUAL,
                'voucher_type' => $meta['voucher_type'] ?? null,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'idempotency_key' => $meta['idempotency_key'] ?? null,
                'memo' => $meta['memo'] ?? null,
                'status' => $status,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'posted_by' => $meta['posted_by'] ?? null,
                'posted_at' => $status === Journal::STATUS_POSTED ? now() : null,
            ], $lines);

            return $journal;
        });
    }

    /**
     * Create the mirror-image journal that cancels out an already-posted one,
     * rather than mutating or deleting it — journals are an immutable audit trail.
     */
    public function reverse(int $journalId, ?string $reason = null, ?int $postedBy = null): Journal
    {
        return $this->reverseJournal($journalId, $reason, $postedBy);
    }

    /**
     * With $period, the reversal is dated on the original's date inside that
     * period (year-end reopen); otherwise it's dated today in today's open period.
     */
    private function reverseJournal(int $journalId, ?string $reason, ?int $postedBy, ?AccountingPeriod $period = null): Journal
    {
        return DB::transaction(function () use ($journalId, $reason, $postedBy, $period) {
            $original = $this->journals->findWithEntries($journalId);

            if ($original === null) {
                throw new InvalidArgumentException('Journal not found.');
            }

            if ($original->status !== Journal::STATUS_POSTED) {
                throw new InvalidArgumentException("Only posted journals can be reversed; journal is {$original->status}.");
            }

            // Reversing it by hand would date the reversal today and leave the
            // year marked closed; reopening the year reverses it properly.
            if ($period === null && $original->source === Journal::SOURCE_YEAR_END_CLOSE) {
                throw new InvalidArgumentException("Journal {$original->journal_number} is a year-end closing entry. Reopen the fiscal year instead of reversing it.");
            }

            // A journal the bank has already cleared is part of a bank
            // reconciliation; reversing it would silently break that
            // reconciliation's statement. Unmatch it there first.
            $reconciled = $original->entries->first(fn ($entry) => $entry->is_reconciled);
            if ($reconciled !== null) {
                throw new InvalidArgumentException(
                    "Journal {$original->journal_number} is matched in bank reconciliation #{$reconciled->bank_reconciliation_id}. Unmatch it there before reversing."
                );
            }

            $reversalLines = $original->entries->map(fn ($entry) => [
                'chart_of_account_id' => $entry->chart_of_account_id,
                'cost_center_id' => $entry->cost_center_id,
                'project_id' => $entry->project_id,
                'party_type' => $entry->party_type,
                'party_id' => $entry->party_id,
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'description' => $reason ?? "Reversal of {$original->journal_number}",
            ])->all();

            $reversal = $this->postJournal($reversalLines, [
                'tenant_id' => $original->tenant_id,
                'company_id' => $original->company_id,
                'branch_id' => $original->branch_id,
                'journal_date' => $period !== null ? $original->journal_date : now(),
                'source' => $original->source,
                'voucher_type' => $original->voucher_type,
                'journal_number_prefix' => $original->voucher_type
                    ? \App\Domains\Accounting\Support\VoucherType::prefix($original->voucher_type)
                    : 'JNL',
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'memo' => $reason ?? "Reversal of {$original->journal_number}",
                'posted_by' => $postedBy,
            ], $period);

            // Releasing the idempotency key lets the source document be
            // posted again now that this posting is cancelled out.
            $original->update([
                'status' => Journal::STATUS_REVERSED,
                'reversed_journal_id' => $reversal->id,
                'idempotency_key' => null,
            ]);

            $this->auditLog->record(
                $original,
                'journal.reversed',
                "Journal {$original->journal_number} reversed by {$reversal->journal_number}",
                [
                    'reversal_journal_id' => $reversal->id,
                    'reason' => $reason,
                ]
            );

            return $reversal;
        });
    }

    public function findByReference(string $referenceType, int $referenceId): Collection
    {
        return $this->journals->findByReference($referenceType, $referenceId);
    }

    public function find(int $id): ?Journal
    {
        return $this->journals->findWithEntries($id);
    }

    public function paginate(array $filters = [], int $perPage = 15)
    {
        return $this->journals->paginateAll($filters, $perPage);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public function posters(): \Illuminate\Support\Collection
    {
        return $this->journals->posters();
    }

    public function trialBalance(AccountingPeriod $period, ?int $costCenterId = null, ?int $projectId = null): Collection
    {
        return $this->journals->trialBalance($period->id, $costCenterId, $projectId);
    }

    /**
     * Cumulative account balances since inception, up to and including
     * $asOfDate — the basis for a Balance Sheet (point-in-time), unlike
     * trialBalance() which is scoped to one period's movements.
     */
    public function balancesAsOf(int $tenantId, \DateTimeInterface $asOfDate, bool $excludeYearEndClose = false): Collection
    {
        return $this->journals->balancesAsOf($tenantId, $asOfDate, $excludeYearEndClose);
    }

    public function forDate(\DateTimeInterface $date): Collection
    {
        return $this->journals->forDate($date);
    }

    /**
     * One account's posted/reversed entries within a date range, in
     * chronological order with a running balance applied — the basis for
     * Cash Book / Bank Book (a General Ledger scoped to one cash/bank ledger
     * and an arbitrary date range rather than a fiscal period).
     *
     * @return array{opening: float, entries: Collection, closing: float}
     */
    public function forAccount(ChartOfAccount $account, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $openingTotals = $this->journals->openingBalance($account->id, $from);
        $opening = $account->signedMovement((float) $openingTotals['debit'], (float) $openingTotals['credit']);

        $running = $opening;
        $entries = $this->journals->entriesForAccount($account->id, $from, $to)->map(function ($entry) use ($account, &$running) {
            $running = round($running + $account->signedMovement((float) $entry->debit, (float) $entry->credit), 2);

            return [
                'entry' => $entry,
                'running_balance' => $running,
            ];
        });

        return [
            'opening' => round($opening, 2),
            'entries' => $entries,
            'closing' => $running,
        ];
    }

    /**
     * Opening balance + chronological entries + running/closing balance for
     * one party (customer/vendor) over a date range — the Party Ledger.
     *
     * Sign convention is fixed per party type rather than derived from each
     * line's own account normal_balance (unlike General Ledger): a customer's
     * entries can land on either Accounts Receivable (debit-normal) or
     * Customer Advances (credit-normal), so a single per-account signedMovement()
     * doesn't hold across the whole statement. Instead — matching the existing
     * hand-rolled Vendor ledger convention and standard ERP party-statement
     * practice — a customer's balance is debit-minus-credit (positive = they
     * owe us), a vendor's is credit-minus-debit (positive = we owe them).
     *
     * @return array{opening: float, entries: Collection, closing: float}
     */
    public function partyLedger(string $partyType, int $partyId, \DateTimeInterface $from, \DateTimeInterface $to, float $seedOpeningBalance = 0.0): array
    {
        $sign = $partyType === JournalEntry::PARTY_VENDOR ? -1 : 1;

        $openingTotals = $this->journals->partyOpeningBalance($partyType, $partyId, $from);
        $opening = round($seedOpeningBalance + $sign * ($openingTotals['debit'] - $openingTotals['credit']), 2);

        $running = $opening;
        $entries = $this->journals->partyLedgerEntries($partyType, $partyId, $from, $to)->map(function ($entry) use ($sign, &$running) {
            $running = round($running + $sign * ($entry->debit - $entry->credit), 2);

            return [
                'entry' => $entry,
                'running_balance' => $running,
            ];
        });

        return [
            'opening' => $opening,
            'entries' => $entries,
            'closing' => $running,
        ];
    }

    /**
     * @return array{opening: array{debit: float, credit: float}, entries: Collection}
     */
    public function generalLedger(int $chartOfAccountId, AccountingPeriod $period): array
    {
        return [
            'opening' => $this->journals->openingBalance($chartOfAccountId, $period->start_date),
            'entries' => $this->journals->ledgerEntries($chartOfAccountId, $period->id),
        ];
    }

    /**
     * Totals are accumulated in integer minor units (paise) rather than floats.
     *
     * The previous implementation summed floats and compared `round($totalDebit, 2) !==
     * round($totalCredit, 2)`. PHP's round() pre-rounding makes that correct for everyday
     * amounts, but float drift grows with line count and magnitude. Integer accumulation
     * is exact at any size, and the `$decimals` scale is what multi-currency posting needs
     * (JPY has no minor unit, KWD has three). Behaviour for 2-decimal journals is unchanged.
     *
     * @param array<int, array{chart_of_account_id: int, debit?: float, credit?: float}> $lines
     * @param int $decimals minor-unit scale of the transaction currency (2 for INR/USD,
     *                      0 for JPY, 3 for KWD)
     */
    private function assertLinesAreBalanced(array $lines, int $decimals = 2): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal requires at least two lines.');
        }

        $scale = 10 ** $decimals;
        $totalDebitMinor = 0;
        $totalCreditMinor = 0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            if (empty($line['chart_of_account_id'])) {
                throw new InvalidArgumentException('Every journal line requires a chart_of_account_id.');
            }

            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Journal line amounts cannot be negative.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('A journal line cannot have both a debit and a credit amount.');
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw new InvalidArgumentException('A journal line must have either a debit or a credit amount.');
            }

            $totalDebitMinor += (int) round($debit * $scale);
            $totalCreditMinor += (int) round($credit * $scale);

            // Kept only for the exception message below, which reports the
            // human-readable amounts rather than paise.
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $accountIds = array_values(array_unique(array_column($lines, 'chart_of_account_id')));
        $groupAccountIds = ChartOfAccount::withoutGlobalScopes()
            ->whereIn('parent_id', $accountIds)
            ->pluck('parent_id')
            ->unique();

        if ($groupAccountIds->isNotEmpty()) {
            $groupAccountCodes = ChartOfAccount::withoutGlobalScopes()
                ->whereIn('id', $groupAccountIds)
                ->pluck('code')
                ->implode(', ');

            throw new InvalidArgumentException(
                "Cannot post directly to a group account with sub-accounts: {$groupAccountCodes}. Select a specific account instead."
            );
        }

        if ($totalDebitMinor !== $totalCreditMinor) {
            throw new InvalidArgumentException(
                "Journal is not balanced: total debit {$totalDebit} does not equal total credit {$totalCredit}."
            );
        }
    }
}
