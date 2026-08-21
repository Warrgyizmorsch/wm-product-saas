<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BalanceSheetController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly FiscalPeriodService $periods,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $allPeriods = AccountingPeriod::with('fiscalYear')->orderByDesc('start_date')->get();

        $period = $request->filled('period_id')
            ? AccountingPeriod::find($request->integer('period_id'))
            : $this->periods->periodForDate(now());

        $sections = [
            'asset' => ['current' => collect(), 'non_current' => collect()],
            'liability' => ['current' => collect(), 'non_current' => collect()],
            'equity' => collect(),
        ];
        $totals = [
            'asset' => 0.0, 'asset_current' => 0.0, 'asset_non_current' => 0.0,
            'liability' => 0.0, 'liability_current' => 0.0, 'liability_non_current' => 0.0,
            'equity' => 0.0,
        ];
        $netIncome = 0.0;
        $isBalanced = true;

        if ($period) {
            $asOfDate = $period->end_date;
            $balances = $this->journals->balancesAsOf(auth()->user()->tenant_id, $asOfDate);

            $incomeTotal = 0.0;
            $expenseTotal = 0.0;

            foreach ($balances as $row) {
                $account = $row->account;

                if ($account === null) {
                    continue;
                }

                $balance = $account->signedMovement((float) $row->debit, (float) $row->credit);

                if ($account->type === ChartOfAccount::TYPE_ASSET) {
                    $bucket = $account->isNonCurrent() ? 'non_current' : 'current';
                    $sections['asset'][$bucket]->push(['account' => $account, 'balance' => $balance]);
                    $totals['asset'] += $balance;
                    $totals["asset_{$bucket}"] += $balance;
                } elseif ($account->type === ChartOfAccount::TYPE_LIABILITY) {
                    $bucket = $account->isNonCurrent() ? 'non_current' : 'current';
                    $sections['liability'][$bucket]->push(['account' => $account, 'balance' => $balance]);
                    $totals['liability'] += $balance;
                    $totals["liability_{$bucket}"] += $balance;
                } elseif ($account->type === ChartOfAccount::TYPE_EQUITY) {
                    $sections['equity']->push(['account' => $account, 'balance' => $balance]);
                    $totals['equity'] += $balance;
                } elseif ($account->type === ChartOfAccount::TYPE_INCOME) {
                    $incomeTotal += $balance;
                } elseif ($account->type === ChartOfAccount::TYPE_EXPENSE) {
                    $expenseTotal += $balance;
                }
            }

            $netIncome = $incomeTotal - $expenseTotal;
            $totals['equity'] += $netIncome;

            $sortByCode = fn ($row) => $row['account']->code;
            foreach (['asset', 'liability'] as $key) {
                $sections[$key]['current'] = $sections[$key]['current']->sortBy($sortByCode)->values();
                $sections[$key]['non_current'] = $sections[$key]['non_current']->sortBy($sortByCode)->values();
            }
            $sections['equity'] = $sections['equity']->sortBy($sortByCode)->values();

            $isBalanced = abs($totals['asset'] - ($totals['liability'] + $totals['equity'])) < 0.01;
        }

        return view('modules.accounting.reports.balance-sheet', [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'sections' => $sections,
            'totals' => $totals,
            'netIncome' => $netIncome,
            'isBalanced' => $isBalanced,
        ]);
    }
}
