<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\OpeningBalanceService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $data = $this->buildReport($request);

        return view('modules.accounting.reports.balance-sheet', $data);
    }

    public function downloadPdf(Request $request)
    {
        $data = $this->buildReport($request);

        if (!$data['period']) {
            return redirect()->route('accounting.reports.balance-sheet')->with('error', 'Select an accounting period first.');
        }

        $pdf = Pdf::loadView('modules.accounting.reports.balance-sheet-pdf', $data);

        return $pdf->download("BalanceSheet_{$data['period']->name}.pdf");
    }

    /**
     * @return array{allPeriods: \Illuminate\Support\Collection, period: ?AccountingPeriod, sections: array, totals: array, netIncome: float, isBalanced: bool}
     */
    private function buildReport(Request $request): array
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
            // journal_date carries a real timestamp, so an in-progress period's end
            // date must cover the full day or same-day postings get excluded by the
            // <= comparison in balancesAsOf().
            $asOfDate = \Illuminate\Support\Carbon::parse($period->end_date)->endOfDay();
            $balances = $this->journals->balancesAsOf(auth()->user()->tenant_id, $asOfDate);

            $incomeTotal = 0.0;
            $expenseTotal = 0.0;

            foreach ($balances as $row) {
                $account = $row->account;

                if ($account === null) {
                    continue;
                }

                $balance = $account->canonicalMovement((float) $row->debit, (float) $row->credit);

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

            $this->reclassifyPartyAdvances($sections, $totals, (int) auth()->user()->tenant_id, $asOfDate);

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

        return [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'sections' => $sections,
            'totals' => $totals,
            'netIncome' => $netIncome,
            'isBalanced' => $isBalanced,
            'openingDifference' => $period
                ? app(OpeningBalanceService::class)->differenceBalance((int) auth()->user()->tenant_id, \Illuminate\Support\Carbon::parse($period->end_date))
                : 0.0,
        ];
    }

    /**
     * Schedule III: a customer in credit is an advance received (a liability)
     * and a supplier in debit an advance paid (an asset); netting them inside
     * Receivable/Payable would understate both sides. Moves those party
     * balances to Advance from Customers / Advance to Suppliers; totals still
     * balance because both sides grow by the same amount.
     */
    private function reclassifyPartyAdvances(array &$sections, array &$totals, int $tenantId, \DateTimeInterface $asOf): void
    {
        $systemAccounts = app(SystemAccountService::class);

        $moves = [
            [SystemAccount::AR, SystemAccount::ADVANCE_FROM_CUSTOMERS, 'asset', 'liability', true],
            [SystemAccount::AP, SystemAccount::ADVANCE_TO_SUPPLIERS, 'liability', 'asset', false],
        ];

        foreach ($moves as [$controlKey, $advanceKey, $from, $to, $debitNormal]) {
            $control = $systemAccounts->get($controlKey, $tenantId);
            $advance = $systemAccounts->get($advanceKey, $tenantId);

            if ($control === null || $advance === null) {
                continue;
            }

            $amount = $this->journals->oppositeSidePartyTotal($tenantId, $control->id, $asOf, $debitNormal);
            if ($amount <= 0) {
                continue;
            }

            foreach ([[$from, $control], [$to, $advance]] as [$side, $account]) {
                $bucket = $sections[$side]['non_current']->contains(fn ($row) => $row['account']->id === $account->id) ? 'non_current' : 'current';
                $rows = $sections[$side][$bucket];
                $index = $rows->search(fn ($row) => $row['account']->id === $account->id);

                if ($index === false) {
                    $rows->push(['account' => $account, 'balance' => $amount]);
                } else {
                    $rows->put($index, ['account' => $account, 'balance' => $rows[$index]['balance'] + $amount]);
                }

                $sections[$side][$bucket] = $rows;
                $totals[$side] += $amount;
                $totals["{$side}_{$bucket}"] += $amount;
            }
        }
    }
}
