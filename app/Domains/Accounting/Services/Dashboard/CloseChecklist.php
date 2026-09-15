<?php

namespace App\Domains\Accounting\Services\Dashboard;

use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use Illuminate\Support\Carbon;

/**
 * Month-end close checklist: everything that should be settled before a
 * period is closed, each with a pass/fail state and a link to fix it.
 */
class CloseChecklist
{
    /** A bank account not reconciled within this many days is flagged. */
    public const RECONCILIATION_STALE_DAYS = 35;

    public function __construct(
        private readonly FiscalPeriodService $periods,
    ) {
    }

    /**
     * @param array{suspense: float, trial_balance_difference: float, inventory: float, receivables: float, payables: float} $position
     * @param float $openReceivables open customer invoices (the AR aging total)
     * @param float $openPayables open vendor bills (the AP aging total)
     * @return array{items: array<string, array{label: string, ok: bool, detail: string, route: string}>, bank_accounts: list<array{account: ChartOfAccount, last_reconciled: ?Carbon, in_progress: bool, stale: bool}>}
     */
    public function build(Carbon $today, array $position, int $postingFailures, float $openReceivables = 0.0, float $openPayables = 0.0): array
    {
        $items = [];

        $current = $this->periods->periodForDate($today);
        $items['current_period'] = [
            'label' => 'Current period open for posting',
            'ok' => $current?->status === AccountingPeriod::STATUS_OPEN,
            'detail' => $current ? "{$current->name} is {$current->status}" : 'No accounting period covers today',
            'route' => 'accounting.fiscal-years.index',
        ];

        $previous = $this->periods->periodForDate($today->copy()->startOfMonth()->subDay());
        if ($previous !== null) {
            $items['previous_period'] = [
                'label' => 'Last month closed',
                'ok' => $previous->status !== AccountingPeriod::STATUS_OPEN,
                'detail' => $previous->status === AccountingPeriod::STATUS_OPEN
                    ? "{$previous->name} is still open"
                    : "{$previous->name} is {$previous->status}",
                'route' => 'accounting.fiscal-years.index',
            ];
        }

        $items['posting_failures'] = $this->countItem('No failed auto-postings', $postingFailures, 'failed posting(s) to retry', 'accounting.posting-failures.index');

        $depreciation = AssetDepreciationSchedule::query()
            ->whereIn('status', [AssetDepreciationSchedule::STATUS_DRAFT, AssetDepreciationSchedule::STATUS_REVIEWED, AssetDepreciationSchedule::STATUS_APPROVED])
            ->count();
        $items['depreciation'] = $this->countItem('Depreciation posted', $depreciation, 'schedule(s) not yet posted', 'accounting.fixed-assets.depreciation.index');

        $assetApprovals = AssetDisposal::query()->where('status', AssetDisposal::STATUS_PENDING_APPROVAL)->count()
            + AssetWriteOff::query()->where('status', AssetWriteOff::STATUS_PENDING_APPROVAL)->count()
            + AssetRevaluation::query()->where('status', AssetRevaluation::STATUS_PENDING_APPROVAL)->count();
        $items['asset_approvals'] = $this->countItem('No asset approvals pending', $assetApprovals, 'disposal/write-off/revaluation(s) awaiting approval', 'accounting.fixed-assets.disposals.index');

        $draftBudgets = Budget::query()->where('status', Budget::STATUS_DRAFT)->count();
        $items['draft_budgets'] = $this->countItem('No budgets awaiting approval', $draftBudgets, 'draft budget(s)', 'accounting.budgets.index');

        $items['suspense'] = [
            'label' => 'Suspense account cleared',
            'ok' => abs($position['suspense']) < 0.005,
            'detail' => abs($position['suspense']) < 0.005 ? 'Balance is zero' : 'Balance '.number_format($position['suspense'], 2).' needs reclassifying',
            'route' => 'accounting.reports.trial-balance',
        ];

        $items['trial_balance'] = [
            'label' => 'Trial balance balanced',
            'ok' => abs($position['trial_balance_difference']) < 0.005,
            'detail' => abs($position['trial_balance_difference']) < 0.005
                ? 'Debits equal credits'
                : 'Out by '.number_format($position['trial_balance_difference'], 2),
            'route' => 'accounting.reports.trial-balance',
        ];

        $items['inventory'] = [
            'label' => 'Inventory balance not negative',
            'ok' => $position['inventory'] >= -0.005,
            'detail' => $position['inventory'] >= -0.005
                ? 'Stock accounts are in order'
                : 'Inventory is '.number_format($position['inventory'], 2).' — stock issued without receipts posted',
            'route' => 'accounting.reports.trial-balance',
        ];

        // The ledger control accounts should equal the open documents behind them;
        // a gap means invoices/bills were never posted, or journals bypassed them.
        $items['receivables_ledger'] = $this->subledgerItem('Receivables ledger matches open invoices', $position['receivables'], $openReceivables, 'accounting.reports.ar-aging');
        $items['payables_ledger'] = $this->subledgerItem('Payables ledger matches open bills', $position['payables'], $openPayables, 'accounting.reports.ap-aging');

        $bankAccounts = $this->bankAccounts($today);
        $stale = collect($bankAccounts)->where('stale', true)->count();
        $items['bank_reconciliation'] = [
            'label' => 'Bank accounts reconciled',
            'ok' => $stale === 0,
            'detail' => $stale === 0
                ? 'All reconciled in the last '.self::RECONCILIATION_STALE_DAYS.' days'
                : "{$stale} account(s) not reconciled in ".self::RECONCILIATION_STALE_DAYS.' days',
            'route' => 'accounting.bank-reconciliation.index',
        ];

        return ['items' => $items, 'bank_accounts' => $bankAccounts];
    }

    /**
     * @return array{label: string, ok: bool, detail: string, route: string}
     */
    private function countItem(string $label, int $count, string $noun, string $route): array
    {
        return [
            'label' => $label,
            'ok' => $count === 0,
            'detail' => $count === 0 ? 'Nothing pending' : "{$count} {$noun}",
            'route' => $route,
        ];
    }

    /**
     * @return array{label: string, ok: bool, detail: string, route: string}
     */
    private function subledgerItem(string $label, float $ledger, float $documents, string $route): array
    {
        // A rupee of tolerance absorbs per-line rounding on invoices and bills.
        $difference = round($ledger - $documents, 2);
        $matches = abs($difference) < 1.0;

        return [
            'label' => $label,
            'ok' => $matches,
            'detail' => $matches
                ? 'Ledger and documents agree'
                : 'Ledger '.number_format($ledger, 2).' vs documents '.number_format($documents, 2).' (difference '.number_format($difference, 2).')',
            'route' => $route,
        ];
    }

    /**
     * Cash-or-bank accounts other than cash-in-hand, with their latest completed
     * reconciliation date.
     *
     * @return list<array{account: ChartOfAccount, last_reconciled: ?Carbon, in_progress: bool, stale: bool}>
     */
    private function bankAccounts(Carbon $today): array
    {
        $accounts = ChartOfAccount::query()
            ->cashOrBank()
            ->active()
            ->where('name', 'not like', '%cash%')
            ->orderBy('code')
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $lastCompleted = BankReconciliation::query()
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->whereIn('chart_of_account_id', $accounts->pluck('id'))
            ->groupBy('chart_of_account_id')
            ->selectRaw('chart_of_account_id, MAX(statement_date) as last_date')
            ->pluck('last_date', 'chart_of_account_id');

        $inProgress = BankReconciliation::query()
            ->where('status', BankReconciliation::STATUS_IN_PROGRESS)
            ->whereIn('chart_of_account_id', $accounts->pluck('id'))
            ->pluck('chart_of_account_id')
            ->flip();

        $staleBefore = $today->copy()->subDays(self::RECONCILIATION_STALE_DAYS);

        return $accounts->map(function (ChartOfAccount $account) use ($lastCompleted, $inProgress, $staleBefore) {
            $last = isset($lastCompleted[$account->id]) ? Carbon::parse($lastCompleted[$account->id]) : null;

            return [
                'account' => $account,
                'last_reconciled' => $last,
                'in_progress' => isset($inProgress[$account->id]),
                'stale' => $last === null || $last->lt($staleBefore),
            ];
        })->all();
    }
}
