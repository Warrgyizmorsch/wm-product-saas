<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CashFlowController extends Controller
{
    /**
     * Working-capital (operating) subtypes on the asset side, excluding cash/bank accounts.
     */
    private const OPERATING_ASSET_SUBTYPES = [
        ChartOfAccount::SUBTYPE_CURRENT_ASSET,
        ChartOfAccount::SUBTYPE_SECURITY_DEPOSIT,
        ChartOfAccount::SUBTYPE_LOANS_ADVANCES,
        ChartOfAccount::SUBTYPE_DUTIES_TAXES,
    ];

    /**
     * Working-capital (operating) subtypes on the liability side.
     */
    private const OPERATING_LIABILITY_SUBTYPES = [
        ChartOfAccount::SUBTYPE_CURRENT_LIABILITY,
        ChartOfAccount::SUBTYPE_PROVISIONS,
        ChartOfAccount::SUBTYPE_SUSPENSE,
        ChartOfAccount::SUBTYPE_DUTIES_TAXES,
    ];

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

        $tenantId = auth()->user()->tenant_id;
        $allPeriods = AccountingPeriod::with('fiscalYear')->orderByDesc('start_date')->get();

        $period = $request->filled('period_id')
            ? AccountingPeriod::find($request->integer('period_id'))
            : $this->periods->periodForDate(now());

        $operating = collect();
        $investing = collect();
        $financing = collect();
        $netProfit = 0.0;
        $operatingTotal = 0.0;
        $investingTotal = 0.0;
        $financingTotal = 0.0;
        $netChangeInCash = 0.0;
        $openingCash = 0.0;
        $closingCash = 0.0;
        $isReconciled = true;

        if ($period) {
            // journal_date carries a real timestamp, not just a date, so the period
            // boundaries must cover the full day or same-day postings get excluded
            // by the <= comparison in balancesAsOf().
            $openingDate = Carbon::parse($period->start_date)->subDay()->endOfDay();
            $closingDate = Carbon::parse($period->end_date)->endOfDay();

            $opening = $this->journals->balancesAsOf($tenantId, $openingDate)->keyBy('chart_of_account_id');
            $closing = $this->journals->balancesAsOf($tenantId, $closingDate)->keyBy('chart_of_account_id');

            foreach ($closing as $accountId => $row) {
                $account = $row->account;

                if ($account === null) {
                    continue;
                }

                $openRow = $opening->get($accountId);
                $deltaDebit = (float) $row->debit - (float) ($openRow->debit ?? 0);
                $deltaCredit = (float) $row->credit - (float) ($openRow->credit ?? 0);
                $movement = $account->canonicalMovement($deltaDebit, $deltaCredit);

                if (abs($movement) < 0.01) {
                    continue;
                }

                if ($account->type === ChartOfAccount::TYPE_INCOME) {
                    $netProfit += $movement;
                } elseif ($account->type === ChartOfAccount::TYPE_EXPENSE) {
                    $netProfit -= $movement;
                } elseif ($account->is_cash_or_bank) {
                    // Reconciliation line, not part of the three activity sections.
                    continue;
                } elseif ($account->type === ChartOfAccount::TYPE_ASSET && in_array($account->subtype, self::OPERATING_ASSET_SUBTYPES, true)) {
                    $operating->push(['account' => $account, 'amount' => -$movement]);
                } elseif ($account->type === ChartOfAccount::TYPE_LIABILITY && in_array($account->subtype, self::OPERATING_LIABILITY_SUBTYPES, true)) {
                    $operating->push(['account' => $account, 'amount' => $movement]);
                } elseif ($account->type === ChartOfAccount::TYPE_ASSET && $account->subtype === ChartOfAccount::SUBTYPE_FIXED_ASSET) {
                    $investing->push(['account' => $account, 'amount' => -$movement]);
                } elseif ($account->type === ChartOfAccount::TYPE_LIABILITY && $account->subtype === ChartOfAccount::SUBTYPE_LONG_TERM_LIABILITY) {
                    $financing->push(['account' => $account, 'amount' => $movement]);
                } elseif ($account->type === ChartOfAccount::TYPE_EQUITY) {
                    $financing->push(['account' => $account, 'amount' => $movement]);
                }
            }

            $sortByCode = fn ($row) => $row['account']->code;
            $operating = $operating->sortBy($sortByCode)->values();
            $investing = $investing->sortBy($sortByCode)->values();
            $financing = $financing->sortBy($sortByCode)->values();

            $operatingTotal = $netProfit + $operating->sum('amount');
            $investingTotal = $investing->sum('amount');
            $financingTotal = $financing->sum('amount');
            $netChangeInCash = $operatingTotal + $investingTotal + $financingTotal;

            $openingCash = $opening->filter(fn ($row) => $row->account?->is_cash_or_bank)
                ->sum(fn ($row) => $row->account->canonicalMovement((float) $row->debit, (float) $row->credit));
            $closingCash = $closing->filter(fn ($row) => $row->account?->is_cash_or_bank)
                ->sum(fn ($row) => $row->account->canonicalMovement((float) $row->debit, (float) $row->credit));

            $isReconciled = abs(($openingCash + $netChangeInCash) - $closingCash) < 0.01;
        }

        return view('modules.accounting.reports.cash-flow', [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'netProfit' => $netProfit,
            'operatingTotal' => $operatingTotal,
            'investingTotal' => $investingTotal,
            'financingTotal' => $financingTotal,
            'netChangeInCash' => $netChangeInCash,
            'openingCash' => $openingCash,
            'closingCash' => $closingCash,
            'isReconciled' => $isReconciled,
        ]);
    }
}
