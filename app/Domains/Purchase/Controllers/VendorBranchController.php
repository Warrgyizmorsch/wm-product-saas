<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Services\Access\AccessService;
use App\Services\PartyStructureService;
use App\Services\PartyVerificationException;
use App\Services\PartyVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorBranchController extends Controller
{
    public function __construct(
        private readonly PartyStructureService $structure,
        private readonly PartyVerificationService $verification,
        private readonly AccessService $access,
    ) {
    }

    /**
     * GET /purchase/vendors/{vendor}/branches
     * Returns active, verified branches and their contacts for PO/RFQ dropdowns.
     */
    public function index(Request $request, Vendor $vendor): JsonResponse
    {
        abort_unless(
            $this->access->allows($request->user(), 'purchase.vendors.view', [
                'tenant_id' => $vendor->tenant_id,
                'company_id' => $vendor->company_id,
                'branch_id' => $vendor->branch_id,
            ]) || $this->access->allows($request->user(), 'purchase.vendor_branches.view', [
                'tenant_id' => $vendor->tenant_id,
            ]),
            403
        );

        $branches = $this->structure->activeVerifiedBranchesFor(
            PartyBranch::PARTY_VENDOR,
            (int) $vendor->id,
            (int) $vendor->tenant_id
        );

        $contacts = PartyContact::query()
            ->forParty(PartyContact::PARTY_VENDOR, (int) $vendor->id)
            ->where('status', 'active')
            ->with('roles')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'vendor_id' => $vendor->id,
            'branches' => $branches->map(fn (PartyBranch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'branch_type' => $b->branch_type,
                'gstin' => $b->gstin,
                'effective_gstin' => $b->effectiveGstin(),
                'gst_state_code' => $b->effectiveStateCode(),
                'address' => $b->formattedAddress(),
                'address_line1' => $b->address_line1,
                'address_line2' => $b->address_line2,
                'city' => $b->city,
                'state' => $b->state,
                'postal_code' => $b->postal_code,
                'country' => $b->country,
                'phone' => $b->phone,
                'email' => $b->email,
                'is_billing' => (bool) $b->is_billing,
                'is_shipping' => (bool) $b->is_shipping,
                'is_default_billing' => (bool) $b->is_default_billing,
                'is_default_shipping' => (bool) $b->is_default_shipping,
                'is_head_office' => (bool) $b->is_head_office,
                'contacts' => $b->contacts->map(fn (PartyContact $c) => $this->formatContact($c))->values()->all(),
            ])->values()->all(),
            'contacts' => $contacts->map(fn (PartyContact $c) => $this->formatContact($c))->values()->all(),
        ]);
    }

    public function store(Request $request, Vendor $vendor): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [PartyBranch::class, PartyBranch::PARTY_VENDOR]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'branch_type' => ['required', 'string', 'in:' . implode(',', array_keys(PartyBranch::TYPES))],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_billing' => ['nullable', 'boolean'],
            'is_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
            'is_default_shipping' => ['nullable', 'boolean'],
        ]);

        $validated['is_billing'] = $request->boolean('is_billing');
        $validated['is_shipping'] = $request->boolean('is_shipping');
        $validated['is_default_billing'] = $request->boolean('is_default_billing');
        $validated['is_default_shipping'] = $request->boolean('is_default_shipping');

        $branch = $this->structure->createBranch($vendor, $validated, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'branch' => $branch], 201);
        }

        $msg = $branch->isVerified()
            ? "Vendor branch '{$branch->name}' added."
            : "Vendor branch '{$branch->name}' added and sent for second-person verification. Vendor remains active.";

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', $msg);
    }

    public function update(Request $request, Vendor $vendor, PartyBranch $partyBranch): RedirectResponse|JsonResponse
    {
        $this->ensureBelongsToVendor($vendor, $partyBranch);
        $this->authorize('update', $partyBranch);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'branch_type' => ['nullable', 'string', 'in:' . implode(',', array_keys(PartyBranch::TYPES))],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_billing' => ['nullable', 'boolean'],
            'is_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
            'is_default_shipping' => ['nullable', 'boolean'],
        ]);

        $validated['is_billing'] = $request->boolean('is_billing');
        $validated['is_shipping'] = $request->boolean('is_shipping');
        $validated['is_default_billing'] = $request->boolean('is_default_billing');
        $validated['is_default_shipping'] = $request->boolean('is_default_shipping');

        $branch = $this->structure->updateBranch($partyBranch, $validated, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'branch' => $branch]);
        }

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', "Vendor branch '{$branch->name}' updated.");
    }

    public function deactivate(Vendor $vendor, PartyBranch $partyBranch): RedirectResponse
    {
        $this->ensureBelongsToVendor($vendor, $partyBranch);
        $this->authorize('update', $partyBranch);

        $this->structure->deactivateBranch($partyBranch);

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', "Vendor branch '{$partyBranch->name}' deactivated.");
    }

    public function activate(Vendor $vendor, PartyBranch $partyBranch): RedirectResponse
    {
        $this->ensureBelongsToVendor($vendor, $partyBranch);
        $this->authorize('update', $partyBranch);

        $this->structure->activateBranch($partyBranch);

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', "Vendor branch '{$partyBranch->name}' activated.");
    }

    public function verify(Request $request, Vendor $vendor, PartyBranch $partyBranch): RedirectResponse
    {
        $this->ensureBelongsToVendor($vendor, $partyBranch);
        abort_unless($this->verification->canVerify($request->user(), $partyBranch), 403);

        $data = $request->validate([
            'checks' => ['nullable', 'array'],
            'checks.*' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->verification->verify($partyBranch, $request->user(), $data['checks'] ?? [], $data['remarks'] ?? null);
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vendor branch '{$partyBranch->name}' verified and activated.");
    }

    public function returnForCorrection(Request $request, Vendor $vendor, PartyBranch $partyBranch): RedirectResponse
    {
        $this->ensureBelongsToVendor($vendor, $partyBranch);
        abort_unless($this->verification->canVerify($request->user(), $partyBranch), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->verification->returnForCorrection($partyBranch, $request->user(), $data['reason']);
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vendor branch '{$partyBranch->name}' returned for correction.");
    }

    public function resubmit(Request $request, Vendor $vendor, PartyBranch $partyBranch): RedirectResponse
    {
        $this->ensureBelongsToVendor($vendor, $partyBranch);
        $this->authorize('update', $partyBranch);

        try {
            $this->verification->resubmit($partyBranch, $request->user());
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vendor branch '{$partyBranch->name}' resubmitted for verification.");
    }

    public function storeContact(Request $request, Vendor $vendor): RedirectResponse|JsonResponse
    {
        $this->authorizeContacts($request, $vendor);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'party_branch_id' => ['nullable', 'integer', 'exists:party_branches,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:contact_roles,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['party_branch_id'])) {
            $branch = PartyBranch::query()->forParty(PartyBranch::PARTY_VENDOR, (int) $vendor->id)->findOrFail($validated['party_branch_id']);
            $validated['party_branch_id'] = $branch->id;
        }

        $validated['is_primary'] = $request->boolean('is_primary');

        $contact = $this->structure->createContact(
            PartyContact::PARTY_VENDOR,
            (int) $vendor->id,
            $validated,
            $validated['role_ids'] ?? [],
            (int) $vendor->tenant_id
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'contact' => $this->formatContact($contact)], 201);
        }

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', "Vendor contact '{$contact->name}' added.");
    }

    public function updateContact(Request $request, Vendor $vendor, PartyContact $contact): RedirectResponse|JsonResponse
    {
        $this->authorizeContacts($request, $vendor);
        abort_unless($contact->party_type === PartyContact::PARTY_VENDOR && (int) $contact->party_id === (int) $vendor->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'party_branch_id' => ['nullable', 'integer', 'exists:party_branches,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:contact_roles,id'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if (! empty($validated['party_branch_id'])) {
            $branch = PartyBranch::query()->forParty(PartyBranch::PARTY_VENDOR, (int) $vendor->id)->findOrFail($validated['party_branch_id']);
            $validated['party_branch_id'] = $branch->id;
        }

        $validated['is_primary'] = $request->boolean('is_primary');

        $updated = $this->structure->updateContact($contact, $validated, $validated['role_ids'] ?? []);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'contact' => $this->formatContact($updated)]);
        }

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', "Vendor contact '{$updated->name}' updated.");
    }

    public function destroyContact(Request $request, Vendor $vendor, PartyContact $contact): RedirectResponse
    {
        $this->authorizeContacts($request, $vendor);
        abort_unless($contact->party_type === PartyContact::PARTY_VENDOR && (int) $contact->party_id === (int) $vendor->id, 404);

        $contact->delete();

        return redirect()->route('purchase.vendors.show', $vendor)->with('success', 'Vendor contact removed.');
    }

    private function ensureBelongsToVendor(Vendor $vendor, PartyBranch $branch): void
    {
        abort_unless(
            $branch->party_type === PartyBranch::PARTY_VENDOR
                && (int) $branch->party_id === (int) $vendor->id
                && (int) $branch->tenant_id === (int) $vendor->tenant_id,
            404
        );
    }

    private function authorizeContacts(Request $request, Vendor $vendor): void
    {
        $allowed = $this->access->allows($request->user(), 'purchase.vendor_contacts.manage', [
            'tenant_id' => $vendor->tenant_id,
            'company_id' => $vendor->company_id,
            'branch_id' => $vendor->branch_id,
        ]);

        abort_unless($allowed, 403);
    }

    /** @return array<string, mixed> */
    private function formatContact(PartyContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'designation' => $contact->designation,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'mobile' => $contact->mobile,
            'party_branch_id' => $contact->party_branch_id,
            'is_primary' => (bool) $contact->is_primary,
            'roles' => $contact->roles->pluck('name')->values()->all(),
            'role_ids' => $contact->roles->pluck('id')->values()->all(),
        ];
    }
}
