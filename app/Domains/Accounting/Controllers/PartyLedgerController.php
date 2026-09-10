<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Platform\Models\Transporter;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PartyLedgerController extends Controller
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

        $partyType = $request->string('party_type')->value();
        $partyId = $request->integer('party_id') ?: null;
        $partyType = in_array($partyType, [JournalEntry::PARTY_CUSTOMER, JournalEntry::PARTY_VENDOR], true) ? $partyType : null;

        $defaultFrom = $this->periods->periodForDate(now())?->fiscalYear?->start_date ?? now()->startOfYear();

        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : Carbon::parse($defaultFrom)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->string('to'))->endOfDay() : now()->endOfDay();

        $party = null;
        $ledger = null;

        if ($partyType && $partyId) {
            $party = $partyType === JournalEntry::PARTY_CUSTOMER
                ? Customer::find($partyId)
                : Vendor::find($partyId);
        }

        if ($party) {
            $ledger = $this->journals->partyLedger($partyType, $partyId, $from, $to, (float) ($party->opening_balance ?? 0));
        }

        return view('modules.accounting.reports.party-ledger', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'transporters' => Transporter::whereNotNull('vendor_id')->orderBy('name')->get(['id', 'name', 'vendor_id']),
            'partyType' => $partyType,
            'partyId' => $partyId,
            'party' => $party,
            'from' => $from,
            'to' => $to,
            'ledger' => $ledger,
        ]);
    }
}
