<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Models\PartyBankChangeRequest;
use App\Services\Access\AccessService;
use App\Services\PartyBankChangeException;
use App\Services\PartyBankChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Callback-verified bank changes for vendors (FRS 2.3, C-16 bank part).
 */
class VendorBankChangeController extends Controller
{
    public function __construct(private readonly PartyBankChangeService $bankChanges)
    {
    }

    /** Queue of requests for approvers. */
    public function index(Request $request): View
    {
        abort_unless($this->bankChanges->canApprove($request->user(), new Vendor(['tenant_id' => $request->user()?->tenant_id])), 403);

        $requests = PartyBankChangeRequest::query()
            ->where('party_type', 'vendor')
            ->with(['vendor', 'requester'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate(15);

        return view('modules.purchase.vendors.bank-changes', ['requests' => $requests, 'bankChanges' => $this->bankChanges]);
    }

    /** Form to ask for a bank change on a vendor. */
    public function create(Request $request, Vendor $vendor): View|RedirectResponse
    {
        $this->authorizeEdit($request);

        if (! $this->bankChanges->isProtected($vendor)) {
            return redirect()->route('purchase.vendors.edit', $vendor->id)
                ->with('warning', 'This vendor\'s bank details can be edited directly.');
        }

        return view('modules.purchase.vendors.bank-change-create', [
            'vendor' => $vendor,
            'openRequest' => $this->bankChanges->openRequest($vendor),
        ]);
    }

    public function store(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeEdit($request);

        $data = $request->validate([
            'new_bank_name' => ['nullable', 'string', 'max:150'],
            'new_account_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'new_ifsc_code' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'new_account_number.regex' => 'Bank account number must contain digits/numbers only.',
        ]);

        try {
            $change = $this->bankChanges->request($vendor, $request->user(), [
                'bank_name' => $data['new_bank_name'] ?? null,
                'account_number' => $data['new_account_number'],
                'ifsc_code' => $data['new_ifsc_code'],
            ], $data['reason']);
        } catch (PartyBankChangeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase.vendors.bank-changes.show', $change->id)
            ->with('success', 'Bank change request raised. The current bank details stay in use until a different person approves it after a callback.');
    }

    public function show(Request $request, PartyBankChangeRequest $bankChange): View
    {
        $vendor = Vendor::query()->findOrFail($bankChange->party_id);

        abort_unless(
            $this->bankChanges->canApprove($request->user(), $vendor)
            || (int) $bankChange->requested_by === (int) $request->user()?->id,
            403
        );

        return view('modules.purchase.vendors.bank-change-show', [
            'change' => $bankChange->load(['requester', 'callbackUser', 'decider']),
            'vendor' => $vendor,
            'canApprove' => $this->bankChanges->canApprove($request->user(), $vendor),
            'isRequester' => (int) $bankChange->requested_by === (int) $request->user()?->id,
        ]);
    }

    public function approve(Request $request, PartyBankChangeRequest $bankChange): RedirectResponse
    {
        $vendor = Vendor::query()->findOrFail($bankChange->party_id);
        abort_unless($this->bankChanges->canApprove($request->user(), $vendor), 403);

        $data = $request->validate([
            'callback_number' => ['required', 'string', 'max:30'],
            'callback_contact' => ['required', 'string', 'max:150'],
            'callback_notes' => ['nullable', 'string', 'max:1000'],
            'number_from_master' => ['nullable', 'boolean'],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        try {
            $this->bankChanges->approve($bankChange, $request->user(), $data);
        } catch (PartyBankChangeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase.vendors.show', $vendor->id)
            ->with('success', 'Bank details updated after callback verification.');
    }

    public function reject(Request $request, PartyBankChangeRequest $bankChange): RedirectResponse
    {
        $vendor = Vendor::query()->findOrFail($bankChange->party_id);
        abort_unless($this->bankChanges->canApprove($request->user(), $vendor), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->bankChanges->reject($bankChange, $request->user(), $data['reason']);
        } catch (PartyBankChangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase.vendors.bank-changes.index')->with('success', 'Bank change request rejected. The bank details were not changed.');
    }

    public function cancel(Request $request, PartyBankChangeRequest $bankChange): RedirectResponse
    {
        $vendor = Vendor::query()->findOrFail($bankChange->party_id);

        try {
            $this->bankChanges->cancel($bankChange, $request->user());
        } catch (PartyBankChangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase.vendors.show', $vendor->id)->with('success', 'Bank change request cancelled.');
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless(
            app(AccessService::class)->allows($request->user(), 'purchase.vendors.edit', ['tenant_id' => $request->user()?->tenant_id]),
            403
        );
    }
}
