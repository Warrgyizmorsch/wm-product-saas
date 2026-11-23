<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\PartyBranch;
use App\Services\PartyVerificationException;
use App\Services\PartyVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Second-person verification of customers (FRS 2.3, C-15).
 */
class CustomerVerificationController extends Controller
{
    public function __construct(private readonly PartyVerificationService $verification)
    {
    }

    /** Queue of customers waiting for a verifier. */
    public function index(Request $request): View
    {
        abort_unless($this->verification->canVerify($request->user(), new Customer(['tenant_id' => $request->user()?->tenant_id])), 403);

        $customers = Customer::query()
            ->pendingVerification()
            ->with('submitter')
            ->orderBy('submitted_at')
            ->paginate(15);

        $pendingBranches = PartyBranch::query()
            ->where('party_type', PartyBranch::PARTY_CUSTOMER)
            ->where('is_head_office', false)
            ->pendingVerification()
            ->with('submitter')
            ->orderBy('submitted_at')
            ->get();

        return view('modules.crm.customers.verification', [
            'customers' => $customers,
            'pendingBranches' => $pendingBranches,
            'verification' => $this->verification,
        ]);
    }

    public function verify(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($this->verification->canVerify($request->user(), $customer), 403);

        $data = $request->validate([
            'checks' => ['nullable', 'array'],
            'checks.*' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->verification->verify($customer, $request->user(), $data['checks'] ?? [], $data['remarks'] ?? null);
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Customer '{$customer->name}' verified and activated.");
    }

    public function returnForCorrection(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($this->verification->canVerify($request->user(), $customer), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->verification->returnForCorrection($customer, $request->user(), $data['reason']);
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Customer '{$customer->name}' returned for correction.");
    }

    public function resubmit(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        try {
            $this->verification->resubmit($customer, $request->user());
        } catch (PartyVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Customer '{$customer->name}' sent for verification again.");
    }
}
