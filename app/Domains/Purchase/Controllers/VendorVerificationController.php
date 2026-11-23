<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Models\PartyBranch;
use App\Services\Access\AccessService;
use App\Services\PartyVerificationException;
use App\Services\PartyVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Second-person verification of vendors (FRS 2.3, C-15).
 */
class VendorVerificationController extends Controller
{
    public function __construct(private readonly PartyVerificationService $verification)
    {
    }

    /** Queue of vendors waiting for a verifier. */
    public function index(Request $request): View
    {
        abort_unless($this->verification->canVerify($request->user(), new Vendor(['tenant_id' => $request->user()?->tenant_id])), 403);

        $vendors = Vendor::query()
            ->pendingVerification()
            ->with('submitter')
            ->orderBy('submitted_at')
            ->paginate(15);

        $pendingBranches = PartyBranch::query()
            ->where('party_type', PartyBranch::PARTY_VENDOR)
            ->where('is_head_office', false)
            ->pendingVerification()
            ->with('submitter')
            ->orderBy('submitted_at')
            ->get();

        return view('modules.purchase.vendors.verification', [
            'vendors' => $vendors,
            'pendingBranches' => $pendingBranches,
            'verification' => $this->verification,
        ]);
    }

    public function verify(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless($this->verification->canVerify($request->user(), $vendor), 403);

        $data = $request->validate([
            'checks' => ['nullable', 'array'],
            'checks.*' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->verification->verify($vendor, $request->user(), $data['checks'] ?? [], $data['remarks'] ?? null);
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vendor '{$vendor->name}' verified and activated.");
    }

    public function returnForCorrection(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless($this->verification->canVerify($request->user(), $vendor), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->verification->returnForCorrection($vendor, $request->user(), $data['reason']);
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vendor '{$vendor->name}' returned for correction.");
    }

    public function resubmit(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(
            app(AccessService::class)->allows($request->user(), 'purchase.vendors.edit', ['tenant_id' => $request->user()?->tenant_id]),
            403
        );

        try {
            $this->verification->resubmit($vendor, $request->user());
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vendor '{$vendor->name}' sent for verification again.");
    }
}
