<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Support\BillAgeing;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ApAgingController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly BillAgeing $ageing,
    ) {
    }

    /**
     * Payables by age from the bill-wise register: every open vendor bill and
     * opening due plus money paid On Account, so each supplier's total is
     * their ledger balance.
     */
    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : Carbon::today();
        $basis = $request->input('basis') === BillAgeing::BASIS_BILL ? BillAgeing::BASIS_BILL : BillAgeing::BASIS_DUE;

        $report = $this->ageing->build('payable', $asOf, $basis);

        return view('modules.accounting.reports.ap-aging', [
            'asOf' => $asOf,
            'basis' => $basis,
            'vendors' => array_map(fn ($p) => $p + ['bills' => $p['documents']], $report['parties']),
            'buckets' => $report['buckets'],
            'onAccount' => $report['onAccount'],
            'grandTotal' => $report['grandTotal'],
        ]);
    }
}
