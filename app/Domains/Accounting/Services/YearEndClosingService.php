<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Support\SystemAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Closing and reopening a fiscal year.
 *
 * Closing posts one journal on the year's last day that zeroes every income
 * and expense account and puts the net profit (or loss) into Reserves &
 * Surplus, then closes the year and its open periods. Balance sheet accounts
 * need no carry-forward entry: their balances are cumulative, so the next
 * year opens with them automatically.
 *
 * Reopening reverses that journal on the same date, so the year's profit is
 * back in its income/expense accounts, and reopens the year. Its periods stay
 * closed until someone with accounting.periods.reopen reopens the one they need.
 */
class YearEndClosingService
{
    public const REFERENCE_TYPE = 'fiscal_year_close';

    public function __construct(
        private readonly JournalService $journals,
        private readonly SystemAccountService $systemAccounts,
        private readonly AccountingAuditLogService $auditLog,
    ) {
    }

    public function close(FiscalYear $fiscalYear, ?int $userId = null): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $userId) {
            $year = $this->lock($fiscalYear);

            if (!$year->isOpen()) {
                throw new InvalidArgumentException("Fiscal year '{$year->name}' is already closed.");
            }

            // Closing carries everything up to this year's end into Reserves,
            // so an earlier year left open would have its profit swept into
            // this year's closing entry and then closed a second time.
            $earlierOpen = FiscalYear::withoutGlobalScopes()
                ->where('tenant_id', $year->tenant_id)
                ->where('status', FiscalYear::STATUS_OPEN)
                ->whereDate('end_date', '<', $year->start_date->toDateString())
                ->orderBy('start_date')
                ->first();
            if ($earlierOpen !== null) {
                throw new InvalidArgumentException("Close fiscal year '{$earlierOpen->name}' first; years are closed in order.");
            }

            $pending = Journal::withoutGlobalScopes()
                ->where('tenant_id', $year->tenant_id)
                ->whereNull('deleted_at')
                ->where('status', Journal::STATUS_PENDING_APPROVAL)
                ->whereBetween('journal_date', [$year->start_date->copy()->startOfDay(), $year->end_date->copy()->endOfDay()])
                ->count();
            if ($pending > 0) {
                throw new InvalidArgumentException("{$pending} journal(s) dated in '{$year->name}' are still waiting for approval. Approve or reject them before closing the year.");
            }

            $lastPeriod = $this->lastPeriod($year);
            $closingJournal = $this->postClosingJournal($year, $lastPeriod, $userId);

            AccountingPeriod::withoutGlobalScopes()
                ->where('fiscal_year_id', $year->id)
                ->where('status', AccountingPeriod::STATUS_OPEN)
                ->get()
                ->each(fn (AccountingPeriod $period) => $period->update([
                    'status' => AccountingPeriod::STATUS_CLOSED,
                    'closed_at' => now(),
                ]));

            $year->update([
                'status' => FiscalYear::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $this->auditLog->record(
                $year,
                'fiscal_year.closed',
                "Fiscal year {$year->name} closed",
                [
                    'closing_journal_id' => $closingJournal?->id,
                    'closing_journal_number' => $closingJournal?->journal_number,
                    'closed_by' => $userId,
                ]
            );

            return $year->fresh();
        });
    }

    public function reopen(FiscalYear $fiscalYear, ?int $userId = null): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $userId) {
            $year = $this->lock($fiscalYear);

            if ($year->isOpen()) {
                throw new InvalidArgumentException("Fiscal year '{$year->name}' is not closed.");
            }

            // A later year's closing entry was worked out from this year's
            // closed books; reopen years newest-first.
            $laterClosed = FiscalYear::withoutGlobalScopes()
                ->where('tenant_id', $year->tenant_id)
                ->where('status', FiscalYear::STATUS_CLOSED)
                ->whereDate('start_date', '>', $year->end_date->toDateString())
                ->orderByDesc('start_date')
                ->first();
            if ($laterClosed !== null) {
                throw new InvalidArgumentException("Reopen fiscal year '{$laterClosed->name}' first; years are reopened newest-first.");
            }

            $closingJournal = $this->journals->activePosting($year->tenant_id, self::REFERENCE_TYPE, $year->id);
            $reversal = null;

            if ($closingJournal !== null) {
                $period = AccountingPeriod::withoutGlobalScopes()->findOrFail($closingJournal->accounting_period_id);
                $reversal = $this->journals->reverseClosingEntry(
                    $closingJournal->id,
                    $period,
                    $userId,
                    "Fiscal year {$year->name} reopened",
                );
            }

            $year->update([
                'status' => FiscalYear::STATUS_OPEN,
                'closed_at' => null,
            ]);

            $this->auditLog->record(
                $year,
                'fiscal_year.reopened',
                "Fiscal year {$year->name} reopened",
                [
                    'reversed_journal_id' => $closingJournal?->id,
                    'reversal_journal_id' => $reversal?->id,
                    'reopened_by' => $userId,
                ]
            );

            return $year->fresh();
        });
    }

    /**
     * Zero every income/expense balance as of the year end into Reserves &
     * Surplus. Null when nothing has been booked to income or expense.
     */
    private function postClosingJournal(FiscalYear $year, AccountingPeriod $lastPeriod, ?int $userId): ?Journal
    {
        $yearEnd = Carbon::parse($year->end_date)->endOfDay();

        $lines = [];
        $netMinor = 0; // debit minus credit, in paise, across the lines below

        foreach ($this->journals->balancesAsOf($year->tenant_id, $yearEnd) as $row) {
            $account = $row->account;

            if ($account === null || !in_array($account->type, [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE], true)) {
                continue;
            }

            // Whole paise, so float sums can't leave the entry a paisa out.
            $balanceMinor = (int) round(((float) $row->debit - (float) $row->credit) * 100);
            if ($balanceMinor === 0) {
                continue;
            }

            $lines[] = [
                'chart_of_account_id' => $account->id,
                'debit' => $balanceMinor < 0 ? -$balanceMinor / 100 : 0,
                'credit' => $balanceMinor > 0 ? $balanceMinor / 100 : 0,
                'description' => "Year-end close of {$account->code} {$account->name}",
            ];
            $netMinor -= $balanceMinor;
        }

        if ($lines === []) {
            return null;
        }

        $reserves = $this->systemAccounts->get(SystemAccount::RESERVES_AND_SURPLUS, $year->tenant_id);
        if ($reserves === null) {
            throw new InvalidArgumentException('Cannot close the year: the Reserves & Surplus account is missing from the chart of accounts.');
        }

        // $netMinor is what the lines above debit minus credit; Reserves takes
        // the other side. A profit leaves the lines debit-heavy → Reserves credit.
        if ($netMinor !== 0) {
            $lines[] = [
                'chart_of_account_id' => $reserves->id,
                'debit' => $netMinor < 0 ? -$netMinor / 100 : 0,
                'credit' => $netMinor > 0 ? $netMinor / 100 : 0,
                'description' => $netMinor > 0 ? "Profit for {$year->name}" : "Loss for {$year->name}",
            ];
        }

        $meta = [
            'tenant_id' => $year->tenant_id,
            'journal_date' => $year->end_date->toDateString(),
            'reference_type' => self::REFERENCE_TYPE,
            'reference_id' => $year->id,
            'journal_number_prefix' => 'YEC',
            'memo' => "Year-end closing entry for {$year->name}",
            'posted_by' => $userId,
        ];
        if ($year->company_id !== null) {
            $meta['company_id'] = $year->company_id;
        }

        return $this->journals->postClosingEntry($lines, $meta, $lastPeriod);
    }

    private function lock(FiscalYear $fiscalYear): FiscalYear
    {
        return FiscalYear::withoutGlobalScopes()->whereKey($fiscalYear->id)->lockForUpdate()->firstOrFail();
    }

    private function lastPeriod(FiscalYear $year): AccountingPeriod
    {
        $period = AccountingPeriod::withoutGlobalScopes()
            ->where('fiscal_year_id', $year->id)
            ->orderByDesc('end_date')
            ->first();

        if ($period === null) {
            throw new InvalidArgumentException("Fiscal year '{$year->name}' has no accounting periods to close.");
        }

        return $period;
    }
}
