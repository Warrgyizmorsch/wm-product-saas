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

class TrialBalanceController extends Controller
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

        $rows = collect();
        $totals = ['debit' => 0.0, 'credit' => 0.0];

        if ($period) {
            $rows = $this->journals->trialBalance($period)->map(function ($row) {
                $account = $row->account;

                return [
                    'account' => $account,
                    'debit' => (float) $row->debit,
                    'credit' => (float) $row->credit,
                    'balance' => $account?->signedMovement((float) $row->debit, (float) $row->credit) ?? 0.0,
                ];
            })->sortBy(fn ($row) => $row['account']?->code)->values();

            $totals['debit'] = $rows->sum('debit');
            $totals['credit'] = $rows->sum('credit');
        }

        return view('modules.accounting.reports.trial-balance', [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }
}
