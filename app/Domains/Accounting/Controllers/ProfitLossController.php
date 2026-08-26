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

class ProfitLossController extends Controller
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
            ChartOfAccount::SUBTYPE_DIRECT_INCOME => collect(),
            ChartOfAccount::SUBTYPE_INDIRECT_INCOME => collect(),
            ChartOfAccount::SUBTYPE_COGS => collect(),
            ChartOfAccount::SUBTYPE_OPERATING_EXPENSE => collect(),
            ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE => collect(),
        ];
        $totals = array_fill_keys(array_keys($sections), 0.0);

        $directIncome = 0.0;
        $indirectIncome = 0.0;
        $cogs = 0.0;
        $operatingExpense = 0.0;
        $indirectExpense = 0.0;

        if ($period) {
            foreach ($this->journals->trialBalance($period) as $row) {
                $account = $row->account;

                if ($account === null || !in_array($account->type, [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE], true)) {
                    continue;
                }

                $movement = $account->signedMovement((float) $row->debit, (float) $row->credit);
                $subtype = $account->subtype;

                if (!isset($sections[$subtype])) {
                    continue;
                }

                $sections[$subtype]->push(['account' => $account, 'amount' => $movement]);
                $totals[$subtype] += $movement;
            }

            $sortByCode = fn ($row) => $row['account']->code;
            foreach ($sections as $subtype => $rows) {
                $sections[$subtype] = $rows->sortBy($sortByCode)->values();
            }

            $directIncome = $totals[ChartOfAccount::SUBTYPE_DIRECT_INCOME];
            $indirectIncome = $totals[ChartOfAccount::SUBTYPE_INDIRECT_INCOME];
            $cogs = $totals[ChartOfAccount::SUBTYPE_COGS];
            $operatingExpense = $totals[ChartOfAccount::SUBTYPE_OPERATING_EXPENSE];
            $indirectExpense = $totals[ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE];
        }

        $grossProfit = $directIncome - $cogs;
        $operatingProfit = $grossProfit - $operatingExpense;
        $netProfit = $operatingProfit + $indirectIncome - $indirectExpense;

        return view('modules.accounting.reports.profit-loss', [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'sections' => $sections,
            'totals' => $totals,
            'directIncome' => $directIncome,
            'indirectIncome' => $indirectIncome,
            'cogs' => $cogs,
            'operatingExpense' => $operatingExpense,
            'indirectExpense' => $indirectExpense,
            'grossProfit' => $grossProfit,
            'operatingProfit' => $operatingProfit,
            'netProfit' => $netProfit,
        ]);
    }
}
