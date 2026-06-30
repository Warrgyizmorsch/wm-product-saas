<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Services\CustomerService;
use App\Domains\CRM\Services\QuotationService;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly CustomerService $customers,
    ) {
    }

    public function index(): View
    {
        return view('modules.crm.quotations.index', [
            'quotations' => $this->quotations->latest(),
        ]);
    }

    public function create(): View
    {
        return view('modules.crm.quotations.create', [
            'quotation'           => null,
            'customers'           => $this->customers->latest(),
            'users'               => User::query()->latest()->get(),
            'nextQuotationNumber' => $this->quotations->getNextQuotationNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'         => ['required', 'exists:customers,id'],
            'lead_id'             => ['nullable', 'integer', 'exists:leads,id'],
            'sales_person_id'     => ['nullable', 'exists:users,id'],
            'quotation_number'    => ['required', 'string', 'max:255'],
            'quotation_date'      => ['required', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount'            => ['nullable', 'numeric', 'min:0'],
            'status'              => ['required', 'string', 'in:Draft,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework'],
            'terms_conditions'    => ['nullable', 'string'],
            'notes'               => ['nullable', 'string'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_name'   => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Save lead_id into the validated data so QuotationService persists it
        if ($request->filled('lead_id')) {
            $validated['lead_id'] = (int) $request->input('lead_id');
        }

        $quotation = $this->quotations->create($validated, $request->input('items'));

        $leadId = $validated['lead_id'] ?? null;
        $this->handleQuotationStatusChange($quotation, $validated['status'], $leadId);

        if ($leadId) {
            $lead = Lead::find($leadId);
            if ($lead) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $lead,
                    'quotation_created',
                    null,
                    $quotation->quotation_number,
                    "Quotation {$quotation->quotation_number} created with status '{$quotation->status}' from Lead stage '{$lead->status}'"
                );
            }
        }

        if ($leadId) {
            return redirect()
                ->route('crm.leads.show', $leadId)
                ->with('success', 'Quotation successfully created!');
        }

        return redirect()
            ->route('crm.quotations.show', $quotation->id)
            ->with('success', 'Quotation successfully created!');
    }

    public function show(int $id): View
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        return view('modules.crm.quotations.show', [
            'quotation' => $quotation,
        ]);
    }

    public function edit(int $id): View
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        return view('modules.crm.quotations.create', [
            'quotation'           => $quotation,
            'customers'           => $this->customers->latest(),
            'users'               => User::query()->latest()->get(),
            'nextQuotationNumber' => $this->quotations->getNextQuotationNumber(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $validated = $request->validate([
            'customer_id'         => ['required', 'exists:customers,id'],
            'lead_id'             => ['nullable', 'integer', 'exists:leads,id'],
            'sales_person_id'     => ['nullable', 'exists:users,id'],
            'quotation_number'    => ['required', 'string', 'max:255'],
            'quotation_date'      => ['required', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount'            => ['nullable', 'numeric', 'min:0'],
            'status'              => ['required', 'string', 'in:Draft,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework'],
            'terms_conditions'    => ['nullable', 'string'],
            'notes'               => ['nullable', 'string'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_name'   => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Preserve lead_id — keep existing lead_id if not passed in form
        if ($request->filled('lead_id')) {
            $validated['lead_id'] = (int) $request->input('lead_id');
        } elseif ($quotation->lead_id) {
            $validated['lead_id'] = $quotation->lead_id;
        }

        $this->quotations->update($quotation, $validated, $request->input('items'));

        $leadId = $validated['lead_id'] ?? null;
        $this->handleQuotationStatusChange($quotation, $validated['status'], $leadId);

        if ($leadId) {
            return redirect()
                ->route('crm.leads.show', $leadId)
                ->with('success', 'Quotation successfully updated!');
        }

        return redirect()
            ->route('crm.quotations.show', $quotation->id)
            ->with('success', 'Quotation successfully updated!');
    }

    private function handleQuotationStatusChange(Quotation $quotation, string $status, ?int $leadId = null): void
    {
        if ($status === 'Accepted') {
            $customer = $quotation->customer;
            if ($customer) {
                $customer->update(['status' => 'active']);
            }

            // Find lead by direct lead_id first (most accurate)
            $lead = null;
            if ($leadId) {
                $lead = Lead::find($leadId);
            }

            // Fallback: if quotation has lead_id stored on model
            if (!$lead && $quotation->lead_id) {
                $lead = Lead::find($quotation->lead_id);
            }

            // Last resort: find by customer email/phone
            if (!$lead && $customer) {
                if ($customer->email) {
                    $lead = Lead::where('email', $customer->email)->first();
                }
                if (!$lead && $customer->phone) {
                    $lead = Lead::where('phone', $customer->phone)->first();
                }
            }

            if ($lead) {
                $lead->update([
                    'status'      => 'Converted',
                    'is_customer' => true,
                ]);
            }
        }
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Draft,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework'],
        ]);

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => $validated['status'],
        ]);

        if ($quotation->lead_id && $oldStatus !== $validated['status']) {
            $lead = Lead::find($quotation->lead_id);
            if ($lead) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $lead,
                    'quotation_status_changed',
                    $oldStatus,
                    $validated['status'],
                    "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to '{$validated['status']}'"
                );
            }
        }

        $this->handleQuotationStatusChange($quotation, $validated['status'], $quotation->lead_id);

        return back()->with('success', 'Quotation status updated successfully!');
    }

    public function destroy(int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->quotations->delete($quotation);

        return redirect()
            ->route('crm.quotations.index')
            ->with('success', 'Quotation successfully deleted.');
    }
}
