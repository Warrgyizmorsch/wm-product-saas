<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use App\Services\PartyStatusException;
use App\Services\PartyStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Making a verified vendor inactive (with a reason) and reactivating it
 * (FRS 2.3, C-16).
 */
class VendorStatusController extends Controller
{
    public function __construct(private readonly PartyStatusService $status)
    {
    }

    public function deactivateForm(Request $request, Vendor $vendor): View|RedirectResponse
    {
        $this->authorizeEdit($request);

        if (! $vendor->isVerified() || ! $this->status->isActive($vendor)) {
            return redirect()->route('purchase.vendors.show', $vendor->id)
                ->with('error', 'Only an active, verified vendor can be made inactive.');
        }

        return view('modules.purchase.vendors.status', [
            'vendor' => $vendor,
            'mode' => 'deactivate',
            'reasons' => $this->status->reasons(),
            'blockedReason' => null,
            'statusService' => $this->status,
        ]);
    }

    public function deactivate(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeEdit($request);

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys($this->status->reasons()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->status->deactivate($vendor, $request->user(), $data['reason'], $data['note'] ?? null);
        } catch (PartyStatusException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' is now inactive.");
    }

    public function reactivateForm(Request $request, Vendor $vendor): View
    {
        $this->authorizeEdit($request);

        return view('modules.purchase.vendors.status', [
            'vendor' => $vendor,
            'mode' => 'reactivate',
            'reasons' => $this->status->reasons(),
            'blockedReason' => $this->status->reactivationBlockedReason($request->user(), $vendor),
            'statusService' => $this->status,
        ]);
    }

    public function reactivate(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeEdit($request);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->status->reactivate($vendor, $request->user(), $data['note'] ?? null);
        } catch (PartyStatusException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' is active again.");
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless(
            app(AccessService::class)->allows($request->user(), 'purchase.vendors.edit', ['tenant_id' => $request->user()?->tenant_id]),
            403
        );
    }
}
