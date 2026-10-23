<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Support\SystemAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one way an opening balance reaches the ledger, for chart-of-accounts
 * ledgers and party masters (customers, vendors, transporters) alike.
 *
 * - Dated on the books' start date (first day of the earliest financial year),
 *   so it is an opening and not a movement in whatever month it was typed in.
 * - Either side: a customer can start in credit (advance received), a vendor
 *   in debit (advance paid).
 * - Offset to one Opening Balance Difference equity account, which nets to
 *   zero once every opening balance is in.
 * - A changed opening reverses the old entry on the same date and posts the
 *   new one; failures throw so the caller can tell the user.
 */
class OpeningBalanceService
{
    public const DEBIT = ChartOfAccount::BALANCE_DEBIT;
    public const CREDIT = ChartOfAccount::BALANCE_CREDIT;

    public function __construct(
        private readonly JournalService $journals,
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * First day of the earliest financial year — where opening balances live.
     */
    public function openingDate(): Carbon
    {
        $start = FiscalYear::query()->orderBy('start_date')->value('start_date');

        if ($start === null) {
            throw new InvalidArgumentException('Create a financial year before entering opening balances.');
        }

        return Carbon::parse($start)->startOfDay();
    }

    /**
     * Make the ledger hold exactly this opening balance for one ledger or party.
     *
     * @param array{
     *     tenant_id: int,
     *     company_id?: int|null,
     *     branch_id?: int|null,
     *     reference_type: string,
     *     reference_id: int,
     *     chart_of_account_id: int,
     *     party_type?: string|null,
     *     party_id?: int|null,
     *     label: string,
     *     source?: string,
     * } $target
     */
    public function sync(array $target, float $amount, string $side): ?Journal
    {
        if (! in_array($side, [self::DEBIT, self::CREDIT], true)) {
            throw new InvalidArgumentException("Opening balance side must be debit or credit, got '{$side}'.");
        }

        if ($amount < 0) {
            // A negative figure is the other side.
            $amount = abs($amount);
            $side = $side === self::DEBIT ? self::CREDIT : self::DEBIT;
        }

        $amount = round($amount, 2);
        $tenantId = (int) $target['tenant_id'];

        return DB::transaction(function () use ($target, $amount, $side, $tenantId) {
            $existing = $this->journals->activePosting($tenantId, $target['reference_type'], (int) $target['reference_id']);

            if ($existing !== null && $this->matches($existing, $target, $amount, $side)) {
                return $existing;
            }

            if ($existing !== null) {
                $this->journals->reverseOnOriginalDate($existing->id, "Opening balance of {$target['label']} changed");
            }

            if ($amount <= 0) {
                return null;
            }

            $offset = $this->differenceAccount($tenantId);
            $isDebit = $side === self::DEBIT;

            return $this->journals->postOnce([
                [
                    'chart_of_account_id' => (int) $target['chart_of_account_id'],
                    'debit' => $isDebit ? $amount : 0,
                    'credit' => $isDebit ? 0 : $amount,
                    'party_type' => $target['party_type'] ?? null,
                    'party_id' => $target['party_id'] ?? null,
                    'description' => "Opening balance - {$target['label']}",
                ],
                [
                    'chart_of_account_id' => $offset->id,
                    'debit' => $isDebit ? 0 : $amount,
                    'credit' => $isDebit ? $amount : 0,
                    'description' => "Opening balance offset - {$target['label']}",
                ],
            ], [
                'tenant_id' => $tenantId,
                'company_id' => $target['company_id'] ?? null,
                'branch_id' => $target['branch_id'] ?? null,
                'journal_date' => $this->openingDate(),
                'source' => $target['source'] ?? Journal::SOURCE_MANUAL,
                'reference_type' => $target['reference_type'],
                'reference_id' => (int) $target['reference_id'],
                'memo' => "Opening balance - {$target['label']}",
            ]);
        });
    }

    /**
     * Already posted exactly as it should be (same account, party, side and
     * amount, on the opening date, against Opening Balance Difference): nothing
     * to do. Entries posted by the old code (dated the day they were typed,
     * offset to Suspense or Reserves) fail this and get re-posted.
     */
    private function matches(Journal $journal, array $target, float $amount, string $side): bool
    {
        if (! Carbon::parse($journal->journal_date)->isSameDay($this->openingDate())) {
            return false;
        }

        $offset = $this->systemAccounts->get(SystemAccount::OPENING_BALANCE_DIFFERENCE, (int) $target['tenant_id']);
        if ($offset === null || ! $journal->entries()->where('chart_of_account_id', $offset->id)->exists()) {
            return false;
        }

        $line = $journal->entries()
            ->where('chart_of_account_id', (int) $target['chart_of_account_id'])
            ->where('party_type', $target['party_type'] ?? null)
            ->where('party_id', $target['party_id'] ?? null)
            ->first();

        if ($line === null) {
            return false;
        }

        $postedSide = (float) $line->debit > 0 ? self::DEBIT : self::CREDIT;
        $postedAmount = round(max((float) $line->debit, (float) $line->credit), 2);

        return $postedSide === $side && abs($postedAmount - $amount) < 0.005;
    }

    /**
     * The Opening Balance Difference account, created under Equity for
     * tenants provisioned before it was part of the default chart.
     */
    public function differenceAccount(int $tenantId): ChartOfAccount
    {
        $account = $this->systemAccounts->get(SystemAccount::OPENING_BALANCE_DIFFERENCE, $tenantId);

        if ($account !== null) {
            return $account;
        }

        $code = SystemAccount::templateCode(SystemAccount::OPENING_BALANCE_DIFFERENCE);

        $taken = ChartOfAccount::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', $code)->exists();

        if ($taken) {
            throw new InvalidArgumentException("Account code {$code} is already used by another ledger; renumber it so the Opening Balance Difference account can be created.");
        }

        $equity = $this->systemAccounts->get('equity', $tenantId);

        return ChartOfAccount::query()->create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'system_key' => SystemAccount::OPENING_BALANCE_DIFFERENCE,
            'name' => 'Opening Balance Difference',
            'type' => ChartOfAccount::TYPE_EQUITY,
            'subtype' => 'capital',
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'parent_id' => $equity?->id,
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    /**
     * What is left on Opening Balance Difference (credit positive). Zero once
     * every opening is in and they agree; anything else means the openings do
     * not balance and the difference is sitting in capital.
     */
    public function differenceBalance(int $tenantId, ?\DateTimeInterface $asOf = null): float
    {
        $account = $this->systemAccounts->get(SystemAccount::OPENING_BALANCE_DIFFERENCE, $tenantId);
        if ($account === null) {
            return 0.0;
        }

        $balance = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journal', fn ($q) => $q->withoutGlobalScopes()
                ->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
                ->when($asOf, fn ($w) => $w->whereDate('journal_date', '<=', $asOf)))
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->value('balance');

        return round((float) $balance, 2);
    }

    /** Accounts Receivable / Payable for a party opening, by party type. */
    public function controlAccount(string $partyType, int $tenantId): ChartOfAccount
    {
        return match ($partyType) {
            JournalEntry::PARTY_CUSTOMER => $this->systemAccounts->require(SystemAccount::AR, $tenantId),
            JournalEntry::PARTY_VENDOR => $this->systemAccounts->require(SystemAccount::AP, $tenantId),
            default => throw new InvalidArgumentException("Unknown party type '{$partyType}'."),
        };
    }
}
