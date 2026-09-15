<?php

namespace App\Domains\Accounting\Services;

use App\Core\Company\CompanyScopeRunner;
use App\Domains\Accounting\Models\AccountingPostingFailure;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\Dashboard\CloseChecklist;
use App\Domains\Accounting\Services\Dashboard\DashboardCache;
use App\Domains\Accounting\Services\Dashboard\LedgerMetrics;
use App\Domains\Accounting\Services\Dashboard\PartyBalances;
use App\Domains\Accounting\Support\DashboardPeriod;
use Illuminate\Support\Carbon;

/**
 * Assembles the Accounting dashboard. Ledger figures, party balances and the
 * close checklist live in Services/Dashboard; GST comes from the same service
 * as the GST Summary report and budget usage from BudgetService, so nothing
 * here can disagree with the report it links to.
 */
class AccountingDashboardService
{
    private const RECENT_JOURNALS = 8;
    private const BUDGET_ALERT_LIMIT = 5;
    private const FORECAST_DAYS = 30;

    /** Monthly GST return due days (of the month after the return month). */
    private const GSTR1_DUE_DAY = 11;
    private const GSTR3B_DUE_DAY = 20;

    public function __construct(
        private readonly LedgerMetrics $ledger,
        private readonly PartyBalances $parties,
        private readonly CloseChecklist $checklist,
        private readonly GstSummaryService $gst,
        private readonly BudgetService $budgets,
        private readonly FiscalPeriodService $periods,
        private readonly DashboardCache $cache,
        private readonly CompanyScopeRunner $companies,
    ) {
    }

    public function fiscalYearStart(Carbon $today): ?Carbon
    {
        return $this->periods->periodForDate($today)?->fiscalYear?->start_date?->copy();
    }

    public function refresh(int $tenantId): void
    {
        $this->cache->flush($tenantId);
    }

    /**
     * summary(), cached until a journal changes or DashboardCache::TTL_SECONDS pass.
     */
    public function cachedSummary(int $tenantId, DashboardPeriod $period, bool $consolidated = false, ?int $costCenterId = null, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        return $this->cache->remember($tenantId, [
            'company' => $consolidated ? 'all' : company_id(),
            'branch' => $consolidated ? 'all' : branch_id(),
            'cost_center' => $costCenterId,
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
            'today' => $today->toDateString(),
        ], fn () => $this->summary($tenantId, $period, $consolidated, $costCenterId, $today) + ['generatedAt' => now()]);
    }

    /**
     * @param bool $consolidated every company and branch of the tenant, instead of the selected one
     * @param int|null $costCenterId limits income, expense, trend and budget figures; balances stay company-wide
     */
    public function summary(int $tenantId, DashboardPeriod $period, bool $consolidated = false, ?int $costCenterId = null, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $build = fn () => $this->build($tenantId, $period, $consolidated, $costCenterId, $today);

        return $consolidated ? $this->companies->acrossCompanies($build) : $build();
    }

    private function build(int $tenantId, DashboardPeriod $period, bool $consolidated, ?int $costCenterId, Carbon $today): array
    {
        $asOf = $period->to->copy()->startOfDay()->min($today);

        $position = $this->ledger->position($this->ledger->balancesAsOf($tenantId, $asOf));
        $movements = $this->ledger->movements($tenantId, $period->from, $period->to, $costCenterId);
        $profitAndLoss = $this->ledger->profitAndLoss($movements);
        $previous = $this->ledger->profitAndLoss($this->ledger->movements($tenantId, $period->previousFrom, $period->previousTo, $costCenterId));
        $trend = $this->ledger->trend($tenantId, $asOf, $costCenterId);

        $cashToday = $asOf->equalTo($today)
            ? $position['cash']
            : $this->ledger->position($this->ledger->balancesAsOf($tenantId, $today))['cash'];
        $receivables = $this->parties->receivables($today, self::FORECAST_DAYS);
        $payables = $this->parties->payables($today, self::FORECAST_DAYS);
        $checklist = $this->checklist->build($today, $position, AccountingPostingFailure::query()->unresolved()->count());

        return [
            'period' => $period,
            'asOf' => $asOf,
            'today' => $today,
            'filters' => [
                'consolidated' => $consolidated,
                'cost_center' => $costCenterId ? CostCenter::query()->find($costCenterId) : null,
            ],
            'kpis' => [
                'income' => $this->compare($profitAndLoss['income'], $previous['income']),
                'expense' => $this->compare($profitAndLoss['expense'], $previous['expense']),
                'net_profit' => $this->compare($profitAndLoss['net_profit'], $previous['net_profit']),
            ],
            'profitAndLoss' => $profitAndLoss,
            'cash' => ['total' => $position['cash'], 'accounts' => $position['cash_accounts']],
            'ratios' => $this->ledger->ratios($position, $profitAndLoss, $period->days()),
            'burn' => $this->ledger->burn($trend, $cashToday),
            'trend' => $trend,
            'expenseBreakdown' => $this->ledger->expenseBreakdown($movements),
            'receivables' => $receivables,
            'payables' => $payables,
            'forecast' => [
                'days' => self::FORECAST_DAYS,
                'opening' => $cashToday,
                'inflow' => $receivables['due_within_horizon'],
                'outflow' => $payables['due_within_horizon'],
                'closing' => round($cashToday + $receivables['due_within_horizon'] - $payables['due_within_horizon'], 2),
            ],
            'gst' => $this->gstPosition($today),
            'tdsPayable' => $position['tds_payable'],
            'budgetAlerts' => $this->budgetAlerts($today, $costCenterId),
            'checklist' => $checklist['items'],
            'bankAccounts' => $checklist['bank_accounts'],
            'recentJournals' => Journal::query()
                ->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
                ->orderByDesc('journal_date')
                ->orderByDesc('id')
                ->limit(self::RECENT_JOURNALS)
                ->get(),
        ];
    }

    /**
     * @return array{current: float, previous: float, change: ?float}
     */
    private function compare(float $current, float $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'change' => abs($previous) < 0.005 ? null : round(($current - $previous) / abs($previous) * 100, 1),
        ];
    }

    /**
     * Last month's return (filed this month) plus what is building up for the
     * current month.
     */
    private function gstPosition(Carbon $today): array
    {
        $returnMonth = $today->copy()->startOfMonth()->subMonthNoOverflow();
        $filing = $this->gst->summary($returnMonth->copy()->startOfDay(), $returnMonth->copy()->endOfMonth());
        $accruing = $this->gst->summary($today->copy()->startOfMonth(), $today->copy()->endOfDay());
        $taxTotal = fn (array $row) => round($row['cgst'] + $row['sgst'] + $row['igst'], 2);

        return [
            'return_month' => $returnMonth->format('M Y'),
            'output' => $taxTotal($filing['output']),
            'input' => $taxTotal($filing['input']),
            'payable' => round($filing['payable']['total'], 2),
            'gstr1_due' => $today->copy()->startOfMonth()->day(self::GSTR1_DUE_DAY),
            'gstr3b_due' => $today->copy()->startOfMonth()->day(self::GSTR3B_DUE_DAY),
            'accruing_month' => $today->format('M Y'),
            'accruing_payable' => round($accruing['payable']['total'], 2),
        ];
    }

    /**
     * Budget lines at or above the warning threshold in approved budgets for
     * the fiscal year containing today, most used first.
     *
     * @return list<array{budget: string, account: ?string, cost_center: ?string, budgeted: float, actual: float, percent: float, status: string}>
     */
    private function budgetAlerts(Carbon $today, ?int $costCenterId): array
    {
        return Budget::query()
            ->with(['fiscalYear', 'lines.account', 'lines.costCenter'])
            ->whereIn('status', [Budget::STATUS_APPROVED, Budget::STATUS_LOCKED])
            ->whereHas('fiscalYear', fn ($query) => $query
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today))
            ->get()
            ->flatMap(fn (Budget $budget) => collect($this->budgets->actualVsBudget($budget, $today->copy()->endOfDay()))
                ->filter(fn (array $row) => $row['has_actuals']
                    && $row['status'] !== BudgetService::STATUS_OK
                    && ($costCenterId === null || (int) $row['line']->cost_center_id === $costCenterId))
                ->map(fn (array $row) => [
                    'budget' => $budget->name,
                    'account' => $row['line']->account?->name,
                    'cost_center' => $row['line']->costCenter?->name,
                    'budgeted' => $row['budgeted'],
                    'actual' => $row['actual'],
                    'percent' => round($row['percent_used'] * 100, 1),
                    'status' => $row['status'],
                ]))
            ->sortByDesc('percent')
            ->take(self::BUDGET_ALERT_LIMIT)
            ->values()
            ->all();
    }
}
