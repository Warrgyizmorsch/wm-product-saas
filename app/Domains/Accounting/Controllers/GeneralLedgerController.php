<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralLedgerController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly FiscalPeriodService $periods,
        private readonly ChartOfAccountsService $accounts,
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

        $accountId = $request->integer('chart_of_account_id') ?: null;
        $account = $accountId ? $this->accounts->find($accountId) : null;

        $ledger = null;
        $rows = collect();
        $runningBalance = 0.0;

        if ($period && $account) {
            $ledger = $this->journals->generalLedger($account->id, $period);
            $runningBalance = $account->signedMovement($ledger['opening']['debit'], $ledger['opening']['credit']);

            $rows = $ledger['entries']->map(function ($entry) use ($account, &$runningBalance) {
                $runningBalance += $account->signedMovement($entry->debit, $entry->credit);

                return [
                    'entry' => $entry,
                    'running_balance' => $runningBalance,
                ];
            });
        }

        return view('modules.accounting.reports.general-ledger', [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'accounts' => $this->accounts->active(),
            'account' => $account,
            'openingBalance' => $ledger ? $account->signedMovement($ledger['opening']['debit'], $ledger['opening']['credit']) : 0.0,
            'rows' => $rows,
            'closingBalance' => $runningBalance,
        ]);
    }
}
