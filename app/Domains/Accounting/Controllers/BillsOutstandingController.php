<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Support\BillAgeing;
use App\Domains\Accounting\Support\PartyDirectory;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Tally's "Bills Outstanding": per party, every open bill with what is still
 * pending on it, plus money On Account and Advances — for any bill-wise
 * ledger (customers, suppliers, employees, transporters ...).
 */
class BillsOutstandingController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly BillAgeing $ageing,
        private readonly BillAllocationService $bills,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $tenantId = (int) (tenant_id() ?? auth()->user()->tenant_id);
        $controls = $this->bills->controlAccounts($tenantId);
        $ledgers = ChartOfAccount::query()->whereIn('id', array_keys($controls))->orderBy('code')->get(['id', 'code', 'name']);

        // "receivable" / "payable" = that whole side; a number = one ledger.
        $choice = (string) $request->input('ledger', 'receivable');
        $ledgerId = ctype_digit($choice) && isset($controls[(int) $choice]) ? (int) $choice : null;
        $side = $ledgerId ? $controls[$ledgerId] : ($choice === 'payable' ? 'payable' : 'receivable');

        $partyType = PartyDirectory::isType($request->input('party_type')) ? $request->input('party_type') : null;
        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : Carbon::today();
        $partyFilter = $request->filled('party') ? (string) $request->input('party') : null;

        $report = $this->ageing->build($side, $asOf, BillAgeing::BASIS_DUE, $ledgerId, $partyType);
        $parties = collect($report['parties']);
        $shown = $partyFilter ? $parties->filter(fn ($p) => "{$p['party_type']}:{$p['party_id']}" === $partyFilter)->values() : $parties;

        return view('modules.accounting.reports.bills-outstanding', [
            'ledger' => $side,
            'choice' => $ledgerId ? (string) $ledgerId : $side,
            'ledgers' => $ledgers,
            'controls' => $controls,
            'partyType' => $partyType,
            'asOf' => $asOf,
            'partyOptions' => $parties->mapWithKeys(fn ($p) => ["{$p['party_type']}:{$p['party_id']}" => "{$p['name']} ({$p['type_label']})"])->sort()->all(),
            'partyFilter' => $partyFilter,
            'parties' => $shown,
            'grandTotal' => round($shown->sum('total'), 2),
        ]);
    }
}
