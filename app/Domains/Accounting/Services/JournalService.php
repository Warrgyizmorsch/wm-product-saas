<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
        private readonly FiscalPeriodService $periods,
        private readonly AccountingAuditLogService $auditLog,
    ) {
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
        $tenantId = $meta['tenant_id'] ?? tenant_id();
        $companyId = $meta['company_id'] ?? company_id();
        $branchId = $meta['branch_id'] ?? branch_id();
        $journalDate = Carbon::parse($meta['journal_date'] ?? now());

        $this->assertLinesAreBalanced($lines);

        return DB::transaction(function () use ($lines, $meta, $tenantId, $companyId, $branchId, $journalDate) {
            $period = $this->periods->assertOpenPeriodForDate($journalDate);

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
                'memo' => $meta['memo'] ?? null,
                'status' => Journal::STATUS_POSTED,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'posted_by' => $meta['posted_by'] ?? null,
                'posted_at' => now(),
            ], $lines);

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
     * Create the mirror-image journal that cancels out an already-posted one,
     * rather than mutating or deleting it — journals are an immutable audit trail.
     */
    public function reverse(int $journalId, ?string $reason = null, ?int $postedBy = null): Journal
    {
        return DB::transaction(function () use ($journalId, $reason, $postedBy) {
            $original = $this->journals->findWithEntries($journalId);

            if ($original === null) {
                throw new InvalidArgumentException('Journal not found.');
            }

            if ($original->status !== Journal::STATUS_POSTED) {
                throw new InvalidArgumentException("Only posted journals can be reversed; journal is {$original->status}.");
            }

            $reversalLines = $original->entries->map(fn ($entry) => [
                'chart_of_account_id' => $entry->chart_of_account_id,
                'cost_center_id' => $entry->cost_center_id,
                'party_type' => $entry->party_type,
                'party_id' => $entry->party_id,
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'description' => $reason ?? "Reversal of {$original->journal_number}",
            ])->all();

            $reversal = $this->post($reversalLines, [
                'tenant_id' => $original->tenant_id,
                'company_id' => $original->company_id,
                'branch_id' => $original->branch_id,
                'journal_date' => now(),
                'source' => $original->source,
                'voucher_type' => $original->voucher_type,
                'journal_number_prefix' => $original->voucher_type
                    ? \App\Domains\Accounting\Support\VoucherType::prefix($original->voucher_type)
                    : 'JNL',
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'memo' => $reason ?? "Reversal of {$original->journal_number}",
                'posted_by' => $postedBy,
            ]);

            $original->update([
                'status' => Journal::STATUS_REVERSED,
                'reversed_journal_id' => $reversal->id,
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

    public function trialBalance(AccountingPeriod $period, ?int $costCenterId = null): Collection
    {
        return $this->journals->trialBalance($period->id, $costCenterId);
    }

    /**
     * Cumulative account balances since inception, up to and including
     * $asOfDate — the basis for a Balance Sheet (point-in-time), unlike
     * trialBalance() which is scoped to one period's movements.
     */
    public function balancesAsOf(int $tenantId, \DateTimeInterface $asOfDate): Collection
    {
        return $this->journals->balancesAsOf($tenantId, $asOfDate);
    }

    public function forDate(\DateTimeInterface $date): Collection
    {
        return $this->journals->forDate($date);
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
     * @param array<int, array{chart_of_account_id: int, debit?: float, credit?: float}> $lines
     */
    private function assertLinesAreBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal requires at least two lines.');
        }

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

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException(
                "Journal is not balanced: total debit {$totalDebit} does not equal total credit {$totalCredit}."
            );
        }
    }
}
