<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Support\PartyDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookups behind the bill-wise box on vouchers and journals: find a party,
 * then list that party's open bills on the chosen ledger.
 */
class BillWiseController extends Controller
{
    public function __construct(
        private readonly PartyDirectory $parties,
        private readonly BillAllocationService $bills,
    ) {
    }

    public function parties(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Journal::class);

        return response()->json(['results' => $this->parties->search((string) $request->query('q', ''))]);
    }

    public function openBills(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Journal::class);

        $validated = $request->validate([
            'party_type' => ['required', 'string'],
            'party_id' => ['required', 'integer'],
            'account_id' => ['required', 'integer', tenant_exists('chart_of_accounts')],
        ]);

        abort_unless(PartyDirectory::isType($validated['party_type']), 422);
        [$type, $id] = $this->parties->canonical($validated['party_type'], (int) $validated['party_id']);
        $ledgerId = (int) $validated['account_id'];
        $kind = $this->bills->controlAccounts((int) tenant_id())[$ledgerId] ?? null;

        if ($kind === null) {
            return response()->json(['bill_wise' => false, 'bills' => [], 'on_account' => 0]);
        }

        $bills = $this->bills->openBills($kind, null, $type, $id, $ledgerId)
            ->sortBy(fn ($row) => [$row->bill->due_date?->toDateString() ?? '', $row->bill->id])
            ->values()
            ->map(fn ($row) => [
                'id' => $row->bill->id,
                'ref_number' => $row->bill->ref_number,
                'bill_date' => $row->bill->bill_date?->toDateString(),
                'due_date' => $row->bill->due_date?->toDateString(),
                // + means the party owes us on this bill (Dr), - means we owe them (Cr).
                'outstanding' => $row->outstanding,
            ]);

        $onAccount = (float) BillAllocation::query()
            ->where('control_account_id', $ledgerId)
            ->where('party_type', $type)->where('party_id', $id)
            ->whereNull('bill_reference_id')
            ->sum('amount');

        return response()->json([
            'bill_wise' => true,
            'party' => "{$type}:{$id}",
            'party_name' => $this->parties->name($type, $id),
            'bills' => $bills,
            'on_account' => round($onAccount, 2),
        ]);
    }
}
