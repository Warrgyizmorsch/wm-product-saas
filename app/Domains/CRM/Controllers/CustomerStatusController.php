<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Http\Controllers\Controller;
use App\Services\PartyStatusException;
use App\Services\PartyStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Making a verified customer inactive (with a reason) and reactivating it
 * (FRS 2.3, C-16).
 */
class CustomerStatusController extends Controller
{
    public function __construct(private readonly PartyStatusService $status)
    {
    }

    public function deactivateForm(Customer $customer): View|RedirectResponse
    {
        $this->authorize('update', $customer);

        if (! $customer->isVerified() || ! $this->status->isActive($customer)) {
            return redirect()->route('crm.customers.show', $customer)
                ->with('error', 'Only an active, verified customer can be made inactive.');
        }

        return view('modules.crm.customers.status', [
            'customer' => $customer,
            'mode' => 'deactivate',
            'reasons' => $this->status->reasons(),
            'blockedReason' => null,
            'statusService' => $this->status,
        ]);
    }

    public function deactivate(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys($this->status->reasons()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->status->deactivate($customer, $request->user(), $data['reason'], $data['note'] ?? null);
        } catch (PartyStatusException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', "Customer '{$customer->name}' is now inactive.");
    }

    public function reactivateForm(Request $request, Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('modules.crm.customers.status', [
            'customer' => $customer,
            'mode' => 'reactivate',
            'reasons' => $this->status->reasons(),
            'blockedReason' => $this->status->reactivationBlockedReason($request->user(), $customer),
            'statusService' => $this->status,
        ]);
    }

    public function reactivate(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->status->reactivate($customer, $request->user(), $data['note'] ?? null);
        } catch (PartyStatusException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', "Customer '{$customer->name}' is active again.");
    }
}
