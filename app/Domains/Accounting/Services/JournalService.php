<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Support\AccountCode;
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
        private readonly CurrencyService $currencies,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    /**
     * Post a balanced double-entry journal.
     *
     * Multi-currency: pass `currency_code` (and optionally `exchange_rate`) in $meta
     * and give line amounts in that currency. They must balance in that currency;
     * each line is then converted to the company's base currency, which is what
     * debit/credit (and so every report) hold. The original amounts are kept in
     * foreign_debit/foreign_credit, and any sub-unit conversion difference is
     * posted to Round Off (5730). Without `currency_code`, or with the base code,
     * lines are base-currency amounts exactly as before.
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
     *     currency_code?: string|null,
     *     exchange_rate?: float|string|null,
     * } $meta
     */
    public function post(array $lines, array $meta = []): Journal
    {
        $meta['tenant_id'] = $meta['tenant_id'] ?? tenant_id();
        $meta['company_id'] = $meta['company_id'] ?? company_id();

        $currency = strtoupper(trim((string) ($meta['currency_code'] ?? '')));
        $baseCurrency = $currency !== '' ? $this->currencies->baseCurrencyForTenant($meta['tenant_id']) : null;

        if ($currency === '' || $currency === $baseCurrency) {
            unset($meta['currency_code'], $meta['exchange_rate']);

            return $this->record($lines, $meta);
        }

        $tenantId = (int) ($meta['tenant_id'] ?? require_tenant_id());
        $journalDate = Carbon::parse($meta['journal_date'] ?? now());

        $rate = (float) ($meta['exchange_rate'] ?? 0);
        if ($rate <= 0) {
            $rate = $this->currencies->rate($currency, $baseCurrency, $journalDate, $tenantId);
        }

        // Balance is a property of the document as entered, so check it in the
        // transaction currency first — conversion can only add rounding noise.
        $this->assertLinesAreBalanced($lines, $this->currencies->decimalsFor($currency));

        $baseDecimals = $this->currencies->decimalsFor($baseCurrency);
        $baseLines = $this->convertToBase($lines, $rate, $baseDecimals, $tenantId);

        $meta['currency_code'] = $currency;
        $meta['exchange_rate'] = $rate;

        return $this->record($baseLines, $meta, $baseDecimals);
    }

    /**
     * Create the mirror-image journal that cancels out an already-posted one,
     * rather than mutating or deleting it — journals are an immutable audit trail.
     *
     * A foreign-currency journal is reversed at its original rate with its
     * original base amounts, so the pair nets to exactly zero in both currencies.
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
                'foreign_debit' => $entry->foreign_credit,
                'foreign_credit' => $entry->foreign_debit,
                'description' => $reason ?? "Reversal of {$original->journal_number}",
            ])->all();

            $decimals = $original->currency_code
                ? $this->currencies->decimalsFor($this->currencies->baseCurrencyForTenant($original->tenant_id))
                : 2;

            $reversal = $this->record($reversalLines, [
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
                'currency_code' => $original->currency_code,
                'exchange_rate' => $original->exchange_rate,
            ], $decimals);

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
     * Persist base-currency lines. Shared by post() and reverse().
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $meta
     */
    private function record(array $lines, array $meta, int $decimals = 2): Journal
    {
        $tenantId = $meta['tenant_id'] ?? tenant_id();
        $companyId = $meta['company_id'] ?? company_id();
        $branchId = $meta['branch_id'] ?? branch_id();
        $journalDate = Carbon::parse($meta['journal_date'] ?? now());

        $this->assertLinesAreBalanced($lines, $decimals);

        return DB::transaction(function () use ($lines, $meta, $tenantId, $companyId, $branchId, $journalDate, $decimals) {
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
                'currency_code' => $meta['currency_code'] ?? null,
                'exchange_rate' => $meta['exchange_rate'] ?? 1,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'memo' => $meta['memo'] ?? null,
                'status' => Journal::STATUS_POSTED,
                'total_debit' => round($totalDebit, $decimals),
                'total_credit' => round($totalCredit, $decimals),
                'posted_by' => $meta['posted_by'] ?? null,
                'posted_at' => now(),
            ], $lines);

            $this->auditLog->record(
                $journal,
                'journal.posted',
                "Journal {$journal->journal_number} posted",
                array_filter([
                    'total_debit' => $journal->total_debit,
                    'total_credit' => $journal->total_credit,
                    'source' => $journal->source,
                    'voucher_type' => $journal->voucher_type,
                    'currency_code' => $journal->currency_code,
                    'exchange_rate' => $journal->currency_code ? $journal->exchange_rate : null,
                ], fn ($value) => $value !== null)
            );

            return $journal;
        });
    }

    /**
     * Convert transaction-currency lines to base currency, keeping the originals.
     *
     * Each line is rounded to the base currency's minor unit independently, so the
     * converted totals can drift by a few minor units even though the originals
     * balance (e.g. 1.00 = 0.50 + 0.50 at a rate of 0.333333 gives 0.33 vs 0.34).
     * That residual is real and must land somewhere — it goes to Round Off.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function convertToBase(array $lines, float $rate, int $baseDecimals, int $tenantId): array
    {
        $scale = 10 ** $baseDecimals;
        $debitMinor = 0;
        $creditMinor = 0;
        $converted = [];

        foreach ($lines as $line) {
            $foreignDebit = (float) ($line['debit'] ?? 0);
            $foreignCredit = (float) ($line['credit'] ?? 0);

            $baseDebit = round($foreignDebit * $rate, $baseDecimals);
            $baseCredit = round($foreignCredit * $rate, $baseDecimals);

            if ($baseDebit == 0.0 && $baseCredit == 0.0) {
                $amount = $foreignDebit ?: $foreignCredit;

                throw new InvalidArgumentException(
                    "A line of {$amount} converts to zero in the base currency at rate {$rate}. Use a larger amount or check the exchange rate."
                );
            }

            $debitMinor += (int) round($baseDebit * $scale);
            $creditMinor += (int) round($baseCredit * $scale);

            $converted[] = array_merge($line, [
                'debit' => $baseDebit,
                'credit' => $baseCredit,
                'foreign_debit' => $foreignDebit > 0 ? $foreignDebit : null,
                'foreign_credit' => $foreignCredit > 0 ? $foreignCredit : null,
            ]);
        }

        $differenceMinor = $debitMinor - $creditMinor;

        if ($differenceMinor !== 0) {
            $amount = abs($differenceMinor) / $scale;
            $roundOff = $this->accounts->findByCode(AccountCode::ROUND_OFF, $tenantId);

            if ($roundOff === null) {
                throw new InvalidArgumentException(
                    "Currency conversion left a rounding difference of {$amount}, but the Round Off account (" . AccountCode::ROUND_OFF . ') does not exist.'
                );
            }

            $converted[] = [
                'chart_of_account_id' => $roundOff->id,
                'debit' => $differenceMinor < 0 ? $amount : 0,
                'credit' => $differenceMinor > 0 ? $amount : 0,
                'description' => 'Currency conversion rounding',
            ];
        }

        return $converted;
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
     * @param int $decimals minor-unit scale of the currency the amounts are in
     *                      (2 for INR/USD, 0 for JPY, 3 for KWD)
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
            // human-readable amounts rather than minor units.
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
