<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\CostCenter;
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

        $costCenterId = $request->filled('cost_center_id') ? $request->integer('cost_center_id') : null;
        $projectId = $request->filled('project_id') ? $request->integer('project_id') : null;
        // closing: balances as at period end, one side per account (the usual
        // trial balance). movement: the period's own debits and credits.
        $basis = $request->input('basis') === 'movement' ? 'movement' : 'closing';

        $rows = collect();
        $totals = ['debit' => 0.0, 'credit' => 0.0];

        if ($period) {
            $source = $basis === 'closing'
                ? $this->journals->trialBalanceAsAt($period, $costCenterId, $projectId)
                : $this->journals->trialBalance($period, $costCenterId, $projectId);

            $rows = $source->map(function ($row) use ($basis) {
                $account = $row->account;
                $debit = (float) $row->debit;
                $credit = (float) $row->credit;

                if ($basis === 'closing') {
                    $net = round($debit - $credit, 2);
                    [$debit, $credit] = [max($net, 0.0), max(-$net, 0.0)];
                }

                return [
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $account?->signedMovement($debit, $credit) ?? 0.0,
                ];
            })
                ->reject(fn ($row) => $basis === 'closing' && $row['debit'] == 0.0 && $row['credit'] == 0.0)
                ->sortBy(fn ($row) => $row['account']?->code)->values();

            $totals['debit'] = $rows->sum('debit');
            $totals['credit'] = $rows->sum('credit');
        }

        return view('modules.accounting.reports.trial-balance', [
            'allPeriods' => $allPeriods,
            'period' => $period,
            'rows' => $rows,
            'totals' => $totals,
            'costCenters' => CostCenter::active()->orderBy('code')->get(),
            'costCenterId' => $costCenterId,
            'projects' => \App\Domains\Projects\Models\Project::query()->orderBy('name')->get(['id', 'project_code', 'name']),
            'projectId' => $projectId,
            'basis' => $basis,
            'openingDifference' => $period
                ? app(\App\Domains\Accounting\Services\OpeningBalanceService::class)->differenceBalance((int) (tenant_id() ?? auth()->user()->tenant_id),\Illuminate\Support\Carbon::parse($period->end_date))
                : 0.0,
        ]);
    }
}
