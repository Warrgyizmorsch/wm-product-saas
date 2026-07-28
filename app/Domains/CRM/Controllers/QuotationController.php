<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Repositories\QuotationRepository;
use App\Domains\CRM\Services\QuotationService;
use App\Domains\Inventory\Models\Product;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationRepository $quotationRepo,
        private readonly QuotationService $quotationService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);
        $quotations = $this->quotationRepo->getPaginatedQuotations($request->all(), 10);

        return view('modules.crm.quotations.index', compact('quotations'));
    }

    public function approvalsIndex(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);
        $quotations = $this->quotationRepo->getPendingApprovals($request->all(), 10);

        return view('modules.crm.quotations.approvals', compact('quotations'));
    }

    public function detailPartial(Quotation $quotation)
    {
        $quotation->load(['lead', 'salesPerson', 'items.product']);
        return view('modules.crm.quotations.detail-partial', compact('quotation'));
    }

    public function create()
    {
        abort(404);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Quotation::class);

        $validated = $request->validate([
            'lead_id'             => ['nullable', 'integer', 'exists:leads,id'],
            'sales_person_id'     => ['nullable', 'exists:users,id'],
            'quotation_number'    => ['required', 'string', 'max:255'],
            'quotation_date'      => ['required', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount'            => ['nullable', 'numeric', 'min:0'],
            'status'              => ['required', 'string', 'in:Draft,Pending Approval,Approved,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework'],
            'terms_conditions'    => ['nullable', 'string'],
            'notes'               => ['nullable', 'string'],
            'items.*.item_name'   => ['nullable', 'string', 'max:255'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (in_array($validated['status'], ['Quotation Sent', 'Accepted', 'Approved'])) {
            return back()->withErrors(['status' => 'A new quotation must start as Draft or Pending Approval.'])->withInput();
        }

        if ($request->filled('lead_id')) {
            $validated['lead_id'] = (int) $request->input('lead_id');
        }

        $quotation = $this->quotationService->create($validated, $request->input('items', []));
        $leadId = $validated['lead_id'] ?? null;
        $this->quotationService->handleQuotationStatusChange($quotation, $validated['status'], $leadId);

        if ($leadId && ($lead = Lead::find($leadId))) {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead, 'quotation_created', null, $quotation->quotation_number,
                "Quotation {$quotation->quotation_number} created with status '{$quotation->status}' from Lead stage '{$lead->status}'"
            );
            return redirect()->route('crm.leads.show', ['lead' => $leadId, 'view_quotation' => 1])->with('success', 'Quotation successfully created!');
        }

        return redirect()->route('crm.quotations.show', $quotation->id)->with('success', 'Quotation successfully created!');
    }

    public function show(int $id): View
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('view', $quotation);

        return view('modules.crm.quotations.show', compact('quotation'));
    }

    public function downloadPdf(int $id)
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('view', $quotation);

        $pdf = Pdf::loadView('modules.crm.quotations.pdf', compact('quotation'));
        return $pdf->download("Quotation_{$quotation->quotation_number}.pdf");
    }

    public function edit(int $id)
    {
        abort(404);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('update', $quotation);

        $validated = $request->validate([
            'lead_id'             => ['nullable', 'integer', 'exists:leads,id'],
            'sales_person_id'     => ['nullable', 'exists:users,id'],
            'quotation_number'    => ['required', 'string', 'max:255'],
            'quotation_date'      => ['required', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount'            => ['nullable', 'numeric', 'min:0'],
            'status'              => ['required', 'string', 'in:Draft,Pending Approval,Approved,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework'],
            'terms_conditions'    => ['nullable', 'string'],
            'notes'               => ['nullable', 'string'],
            'items.*.item_name'   => ['nullable', 'string', 'max:255'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $newStatus = $validated['status'];
        if ($newStatus !== $quotation->status) {
            if (in_array($newStatus, ['Quotation Sent', 'Accepted']) && !in_array($quotation->status, ['Approved', 'Quotation Sent', 'Accepted'])) {
                return back()->withErrors(['status' => 'A quotation must be Approved before it can be Sent or Accepted.'])->withInput();
            }
            if ($newStatus === 'Approved' && $quotation->status !== 'Approved') {
                return back()->withErrors(['status' => 'Quotation approval must be performed using the Approve button.'])->withInput();
            }
        }

        if ($request->filled('lead_id')) {
            $validated['lead_id'] = (int) $request->input('lead_id');
        } elseif ($quotation->lead_id) {
            $validated['lead_id'] = $quotation->lead_id;
        }

        $newQuotation = $this->quotationService->update($quotation, $validated, $request->input('items', []));
        $leadId = $validated['lead_id'] ?? null;
        $this->quotationService->handleQuotationStatusChange($newQuotation, $validated['status'], $leadId);

        if ($leadId) {
            return redirect()->route('crm.leads.show', ['lead' => $leadId, 'view_quotation' => 1])->with('success', 'Quotation successfully updated!');
        }

        return redirect()->route('crm.quotations.show', $newQuotation->id)->with('success', 'Quotation successfully updated!');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('update', $quotation);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Draft,Pending Approval,Approved,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework,Converted'],
        ]);

        $newStatus = $validated['status'];
        if ($newStatus !== $quotation->status) {
            if (in_array($newStatus, ['Quotation Sent', 'Accepted']) && !in_array($quotation->status, ['Approved', 'Quotation Sent', 'Accepted'])) {
                return back()->withErrors(['status' => 'A quotation must be Approved before it can be Sent or Accepted.']);
            }
            if ($newStatus === 'Approved' && $quotation->status !== 'Approved') {
                return back()->withErrors(['status' => 'Quotation approval must be performed using the Approve button.']);
            }
        }

        $oldStatus = $quotation->status;
        $this->quotationRepo->update($quotation, ['status' => $newStatus]);

        if ($quotation->lead_id && $oldStatus !== $newStatus && ($lead = Lead::find($quotation->lead_id))) {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead, 'quotation_status_changed', $oldStatus, $newStatus,
                "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to '{$newStatus}'"
            );
        }

        $this->quotationService->handleQuotationStatusChange($quotation, $newStatus, $quotation->lead_id);
        return back()->with('success', 'Quotation status updated successfully!');
    }

    public function approve(int $id): RedirectResponse
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('approve', $quotation);

        $oldStatus = $quotation->status;
        $this->quotationRepo->update($quotation, ['status' => 'Approved']);

        if ($quotation->lead_id && $oldStatus !== 'Approved' && ($lead = Lead::find($quotation->lead_id))) {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead, 'quotation_status_changed', $oldStatus, 'Approved',
                "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to 'Approved'"
            );
        }

        $this->quotationService->handleQuotationStatusChange($quotation, 'Approved', $quotation->lead_id);
        return back()->with('success', 'Quotation approved successfully!');
    }

    public function reject(int $id): RedirectResponse
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('approve', $quotation);

        $oldStatus = $quotation->status;
        $this->quotationRepo->update($quotation, ['status' => 'Rejected']);

        if ($quotation->lead_id && $oldStatus !== 'Rejected' && ($lead = Lead::find($quotation->lead_id))) {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead, 'quotation_status_changed', $oldStatus, 'Rejected',
                "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to 'Rejected'"
            );
        }

        $this->quotationService->handleQuotationStatusChange($quotation, 'Rejected', $quotation->lead_id);
        return back()->with('success', 'Quotation rejected successfully!');
    }

    public function destroy(int $id): RedirectResponse
    {
        $quotation = $this->quotationRepo->find($id);
        if (!$quotation) abort(404, 'Quotation not found.');
        $this->authorize('delete', $quotation);

        $this->quotationService->delete($quotation);
        return redirect()->route('crm.quotations.index')->with('success', 'Quotation successfully deleted.');
    }
}
