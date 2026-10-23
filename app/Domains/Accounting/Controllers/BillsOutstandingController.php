<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Support\BillAgeing;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Tally's "Bills Outstanding": per party, every open bill with what is still
 * pending on it, plus money On Account — for receivables or payables.
 */
class BillsOutstandingController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly BillAgeing $ageing,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $ledger = $request->input('ledger') === 'payable' ? 'payable' : 'receivable';
        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : Carbon::today();
        $partyFilter = $request->filled('party') ? (string) $request->input('party') : null;

        $report = $this->ageing->build($ledger, $asOf, BillAgeing::BASIS_DUE);
        $parties = collect($report['parties']);

        return view('modules.accounting.reports.bills-outstanding', [
            'ledger' => $ledger,
            'asOf' => $asOf,
            'partyOptions' => $parties->mapWithKeys(fn ($p) => ["{$p['party_type']}:{$p['party_id']}" => $p['name']])->sort()->all(),
            'partyFilter' => $partyFilter,
            'parties' => $partyFilter ? $parties->filter(fn ($p) => "{$p['party_type']}:{$p['party_id']}" === $partyFilter)->values() : $parties,
            'grandTotal' => $partyFilter
                ? $parties->filter(fn ($p) => "{$p['party_type']}:{$p['party_id']}" === $partyFilter)->sum('total')
                : $report['grandTotal'],
        ]);
    }
}
