<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\BillSettlement;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\BillWise\BillSettlementService;
use App\Domains\Accounting\Services\BillWise\OpeningBreakupService;
use App\Domains\Accounting\Support\PartyDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Accounting → Bill Settlement: match a party's On Account / Advance money
 * against its open bills (Tally's bill-wise adjustment).
 */
class BillSettlementController extends Controller
{
    public function __construct(
        private readonly BillSettlementService $settlements,
        private readonly PartyDirectory $parties,
        private readonly OpeningBreakupService $openings,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Journal::class);

        [$partyType, $partyId] = $this->party((string) $request->query('party', ''));
        $data = [
            'party' => $partyType ? "{$partyType}:{$partyId}" : '',
            'partyName' => $partyType ? $this->parties->name($partyType, $partyId) : null,
            'partyLabel' => $partyType ? PartyDirectory::label($partyType) : null,
            'bills' => collect(),
            'pieces' => collect(),
            'suggestion' => ['bills' => [], 'pieces' => []],
            'history' => collect(),
            'ledgers' => collect(),
            'opening' => null,
            'canSettle' => $request->user()->can('post', Journal::class),
        ];

        if ($partyType) {
            $data['bills'] = $this->settlements->openBills($partyType, $partyId);
            $data['pieces'] = $this->settlements->unmatched($partyType, $partyId);
            $data['suggestion'] = $this->settlements->suggest($partyType, $partyId);
            $data['history'] = BillSettlement::query()->with(['journal:id,journal_number', 'creator:id,name'])
                ->where('party_type', $partyType)->where('party_id', $partyId)
                ->latest('id')->limit(20)->get();
            $data['ledgers'] = ChartOfAccount::query()
                ->whereIn('id', $data['bills']->pluck('ledger_id')->merge($data['pieces']->pluck('ledger_id'))->unique())
                ->get(['id', 'code', 'name'])->keyBy('id');
            $data['opening'] = $this->openings->describe($partyType, $partyId);
        }

        return view('modules.accounting.bill-wise.settlement', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        $validated = $request->validate([
            'party' => ['required', 'string', 'max:40'],
            'settled_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'bills' => ['nullable', 'array'],
            'bills.*' => ['nullable', 'numeric', 'min:0'],
            'pieces' => ['nullable', 'array'],
            'pieces.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        [$partyType, $partyId] = $this->party($validated['party']);
        abort_unless($partyType, 422);

        try {
            $settlement = $this->settlements->settle(
                $partyType,
                $partyId,
                $validated['bills'] ?? [],
                $validated['pieces'] ?? [],
                $validated['note'] ?? null,
                $validated['settled_on'],
                auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.bill-wise.settlement', ['party' => $validated['party']])
            ->with('success', 'Adjusted ' . number_format((float) $settlement->amount, 2) . ' against bills'
                . ($settlement->journal_id ? ' (advance moved by a transfer journal).' : '.'));
    }

    public function destroy(Request $request, BillSettlement $settlement): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        $party = "{$settlement->party_type}:{$settlement->party_id}";

        try {
            $this->settlements->undo($settlement, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect()->route('accounting.bill-wise.settlement', ['party' => $party])
            ->with('success', 'Adjustment undone — the bills are open again.');
    }

    public function openingBreakup(Request $request): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        $validated = $request->validate([
            'party' => ['required', 'string', 'max:40'],
            'rows' => ['nullable', 'array', 'max:200'],
            'rows.*.ref' => ['nullable', 'string', 'max:100'],
            'rows.*.bill_date' => ['nullable', 'date'],
            'rows.*.due_date' => ['nullable', 'date'],
            'rows.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        [$partyType, $partyId] = $this->party($validated['party']);
        abort_unless($partyType, 422);

        try {
            $this->openings->split($partyType, $partyId, array_values($validated['rows'] ?? []), auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['opening' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.bill-wise.settlement', ['party' => $validated['party']])
            ->with('success', empty(array_filter($validated['rows'] ?? [], fn ($r) => ! empty($r['ref']))) ? 'Opening balance is one bill again.' : 'Opening balance broken into old bills.');
    }

    /** @return array{0: ?string, 1: ?int} */
    private function party(string $value): array
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);

        if (! PartyDirectory::isType($type) || ! ctype_digit((string) $id) || ! $this->parties->exists($type, (int) $id)) {
            return [null, null];
        }

        return $this->parties->canonical($type, (int) $id);
    }
}
