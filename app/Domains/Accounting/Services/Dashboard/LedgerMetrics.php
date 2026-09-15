<?php

namespace App\Domains\Accounting\Services\Dashboard;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Support\AccountCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Figures derived from the general ledger: profit & loss, financial position,
 * ratios, trend and burn. Every query counts posted AND reversed journals (a
 * reversal and its original net to zero), matching the Trial Balance and
 * Balance Sheet the dashboard links to.
 */
class LedgerMetrics
{
    private const TREND_MONTHS = 6;
    private const BURN_MONTHS = 3;

    /** 1200 Inventory and 1201–1203 stock accounts in the default chart. */
    private const INVENTORY_CODE_PREFIX = '120';
    private const TDS_PAYABLE_CODE = '2140';
    private const SUSPENSE_CODE = '2900';

    public function __construct(
        private readonly JournalRepositoryInterface $journals,
    ) {
    }

    public function balancesAsOf(int $tenantId, Carbon $date): Collection
    {
        // End of day: journal_date can carry a time, and today's journals must count.
        return $this->journals->balancesAsOf($tenantId, $date->copy()->endOfDay());
    }

    public function movements(int $tenantId, Carbon $from, Carbon $to, ?int $costCenterId = null): Collection
    {
        return $this->journals->movementsBetween($tenantId, $from->copy()->startOfDay(), $to->copy()->endOfDay(), $costCenterId);
    }

    /**
     * @return array{income: float, expense: float, direct_income: float, cogs: float, gross_profit: float, net_profit: float, gross_margin: ?float, net_margin: ?float}
     */
    public function profitAndLoss(Collection $movements): array
    {
        $totals = ['income' => 0.0, 'expense' => 0.0, 'direct_income' => 0.0, 'cogs' => 0.0];

        foreach ($movements as $row) {
            $account = $row->account;

            if ($account === null || ! in_array($account->type, [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE], true)) {
                continue;
            }

            $amount = $account->canonicalMovement((float) $row->debit, (float) $row->credit);
            $totals[$account->type] += $amount;

            if ($account->subtype === ChartOfAccount::SUBTYPE_DIRECT_INCOME) {
                $totals['direct_income'] += $amount;
            } elseif ($account->subtype === ChartOfAccount::SUBTYPE_COGS) {
                $totals['cogs'] += $amount;
            }
        }

        $totals = array_map(fn (float $value) => round($value, 2), $totals);
        $grossProfit = round($totals['direct_income'] - $totals['cogs'], 2);
        $netProfit = round($totals['income'] - $totals['expense'], 2);

        return $totals + [
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'gross_margin' => $totals['direct_income'] != 0.0 ? round($grossProfit / $totals['direct_income'] * 100, 1) : null,
            'net_margin' => $totals['income'] != 0.0 ? round($netProfit / $totals['income'] * 100, 1) : null,
        ];
    }

    /**
     * Balance-sheet position from cumulative balances.
     *
     * @return array{cash: float, cash_accounts: Collection, current_assets: float, inventory: float, current_liabilities: float, receivables: float, payables: float, tds_payable: float, suspense: float, trial_balance_difference: float}
     */
    public function position(Collection $balances): array
    {
        $position = [
            'cash' => 0.0, 'current_assets' => 0.0, 'inventory' => 0.0, 'current_liabilities' => 0.0,
            'receivables' => 0.0, 'payables' => 0.0, 'tds_payable' => 0.0, 'suspense' => 0.0,
        ];
        $cashAccounts = collect();
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($balances as $row) {
            $totalDebit += (float) $row->debit;
            $totalCredit += (float) $row->credit;
            $account = $row->account;

            if ($account === null) {
                continue;
            }

            $amount = $account->canonicalMovement((float) $row->debit, (float) $row->credit);
            $isCurrent = ! in_array($account->subtype, ChartOfAccount::NON_CURRENT_SUBTYPES, true);

            if ($account->type === ChartOfAccount::TYPE_ASSET && $isCurrent) {
                $position['current_assets'] += $amount;

                if (str_starts_with((string) $account->code, self::INVENTORY_CODE_PREFIX)) {
                    $position['inventory'] += $amount;
                }
            } elseif ($account->type === ChartOfAccount::TYPE_LIABILITY && $isCurrent) {
                $position['current_liabilities'] += $amount;
            }

            if ($account->is_cash_or_bank) {
                $position['cash'] += $amount;
                $cashAccounts->push(['account' => $account, 'balance' => round($amount, 2)]);
            }

            match ((string) $account->code) {
                AccountCode::AR => $position['receivables'] += $amount,
                AccountCode::AP => $position['payables'] += $amount,
                self::TDS_PAYABLE_CODE => $position['tds_payable'] += $amount,
                self::SUSPENSE_CODE => $position['suspense'] += $amount,
                default => null,
            };
        }

        return array_map(fn (float $value) => round($value, 2), $position) + [
            'cash_accounts' => $cashAccounts->sortBy(fn (array $row) => $row['account']->code)->values(),
            'trial_balance_difference' => round($totalDebit - $totalCredit, 2),
        ];
    }

    /**
     * @return array{gross_margin: ?float, net_margin: ?float, current_ratio: ?float, quick_ratio: ?float, working_capital: float, dso: ?int, dpo: ?int}
     */
    public function ratios(array $position, array $profitAndLoss, int $days): array
    {
        $currentLiabilities = $position['current_liabilities'];
        $purchases = $profitAndLoss['cogs'] > 0 ? $profitAndLoss['cogs'] : $profitAndLoss['expense'];

        return [
            'gross_margin' => $profitAndLoss['gross_margin'],
            'net_margin' => $profitAndLoss['net_margin'],
            'current_ratio' => $currentLiabilities > 0 ? round($position['current_assets'] / $currentLiabilities, 2) : null,
            'quick_ratio' => $currentLiabilities > 0 ? round(($position['current_assets'] - $position['inventory']) / $currentLiabilities, 2) : null,
            'working_capital' => round($position['current_assets'] - $currentLiabilities, 2),
            'dso' => $profitAndLoss['income'] > 0 ? (int) round($position['receivables'] / $profitAndLoss['income'] * $days) : null,
            'dpo' => $purchases > 0 ? (int) round($position['payables'] / $purchases * $days) : null,
        ];
    }

    /**
     * Income and expense per calendar month for the TREND_MONTHS months ending
     * with the month of $end.
     *
     * @return array{labels: list<string>, income: list<float>, expense: list<float>}
     */
    public function trend(int $tenantId, Carbon $end, ?int $costCenterId = null): array
    {
        $start = $end->copy()->startOfMonth()->subMonthsNoOverflow(self::TREND_MONTHS - 1);
        $months = [];

        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $months[$month->format('Y-m')] = ['label' => $month->format('M Y'), 'income' => 0.0, 'expense' => 0.0];
        }

        foreach ($this->journals->dailyMovements($tenantId, $start, $end->copy()->endOfDay(), $costCenterId) as $row) {
            $account = $row->account;
            $key = Carbon::parse($row->journal_date)->format('Y-m');

            if ($account === null || ! isset($months[$key]) || ! in_array($account->type, [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE], true)) {
                continue;
            }

            $months[$key][$account->type] += $account->canonicalMovement((float) $row->debit, (float) $row->credit);
        }

        return [
            'labels' => array_column($months, 'label'),
            'income' => array_map(fn (array $month) => round($month['income'], 2), array_values($months)),
            'expense' => array_map(fn (array $month) => round($month['expense'], 2), array_values($months)),
        ];
    }

    /**
     * Average monthly spend and net burn over the last BURN_MONTHS months of
     * the trend; runway is how many months cash lasts at that net burn.
     *
     * @return array{monthly_expense: float, net_burn: float, runway_months: ?float}
     */
    public function burn(array $trend, float $cash): array
    {
        $expense = array_slice($trend['expense'], -self::BURN_MONTHS);
        $income = array_slice($trend['income'], -self::BURN_MONTHS);
        $months = max(count($expense), 1);
        $netBurn = round((array_sum($expense) - array_sum($income)) / $months, 2);

        return [
            'monthly_expense' => round(array_sum($expense) / $months, 2),
            'net_burn' => $netBurn,
            'runway_months' => $netBurn > 0 ? round(max($cash, 0) / $netBurn, 1) : null,
        ];
    }

    /**
     * Largest expense accounts in the period, the rest folded into "Other".
     *
     * @return list<array{label: string, amount: float}>
     */
    public function expenseBreakdown(Collection $movements, int $top = 5): array
    {
        $expenses = $movements
            ->filter(fn ($row) => $row->account?->type === ChartOfAccount::TYPE_EXPENSE)
            ->map(fn ($row) => [
                'label' => $row->account->name,
                'amount' => round($row->account->canonicalMovement((float) $row->debit, (float) $row->credit), 2),
            ])
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        $breakdown = $expenses->take($top)->all();
        $other = round($expenses->slice($top)->sum('amount'), 2);

        if ($other > 0) {
            $breakdown[] = ['label' => 'Other', 'amount' => $other];
        }

        return $breakdown;
    }
}
