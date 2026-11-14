<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Support\BillAgeing;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ArAgingController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly BillAgeing $ageing,
    ) {
    }

    /**
     * Receivables by age from the bill-wise register: every open bill
     * (invoices, opening dues, returns) plus money received On Account, so
     * each customer's total is their ledger balance.
     */
    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : Carbon::today();
        $basis = $request->input('basis') === BillAgeing::BASIS_BILL ? BillAgeing::BASIS_BILL : BillAgeing::BASIS_DUE;

        // Customers on Accounts Receivable only (other bill-wise ledgers such as
        // advances to employees are in Bills Outstanding).
        $ledgerId = app(\App\Domains\Accounting\Services\SystemAccountService::class)->get(\App\Domains\Accounting\Support\SystemAccount::AR, (int) (tenant_id() ?? auth()->user()->tenant_id))?->id;
        $report = $this->ageing->build('receivable', $asOf, $basis, $ledgerId);

        return view('modules.accounting.reports.ar-aging', [
            'asOf' => $asOf,
            'basis' => $basis,
            'customers' => array_map(fn ($p) => ['invoices' => $p['documents']] + $p, $report['parties']),
            'buckets' => $report['buckets'],
            'onAccount' => $report['onAccount'],
            'advance' => $report['advance'],
            'grandTotal' => $report['grandTotal'],
        ]);
    }
}
