<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Services\CustomerService;
use App\Domains\CRM\Services\QuotationService;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
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
        private readonly QuotationService $quotations,
        private readonly CustomerService $customers,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);

        $query = Quotation::query()
            ->with(['lead', 'salesPerson'])
            ->where('is_current', true);

        // Exclude Draft and Pending Approval statuses by default
        if (!$request->has('status') && !$request->has('search')) {
            $query->whereNotIn('status', ['Draft', 'Pending Approval']);
        }

        // Search Keywords
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $cleanSearch = str_replace('QT-', '', $search);
                $q->where('quotation_number', 'like', "%{$cleanSearch}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($leadQ) use ($search) {
                      $leadQ->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'quotation_date');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['quotation_number', 'quotation_date', 'expiry_date', 'total_amount', 'status'];
        if ($sortBy === 'customer_name') {
            $query->join('leads', 'quotations.lead_id', '=', 'leads.id')
                  ->select('quotations.*')
                  ->orderBy(\Illuminate\Support\Facades\DB::raw('COALESCE(leads.company_name, leads.contact_person)'), $sortOrder);
        } elseif (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('quotation_date', 'desc');
        }

        $quotations = $query->paginate(10)->withQueryString();

        return view('modules.crm.quotations.index', compact('quotations'));
    }

    public function approvalsIndex(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);

        $query = Quotation::query()
            ->with(['lead', 'salesPerson'])
            ->where('is_current', true);

        // For Approvals, by default filter by Pending Approval if no status/search is specified
        if (!$request->has('status') && !$request->has('search')) {
            $query->where('status', 'Pending Approval');
        }

        // Search Keywords
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $cleanSearch = str_replace('QT-', '', $search);
                $q->where('quotation_number', 'like', "%{$cleanSearch}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($leadQ) use ($search) {
                      $leadQ->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'quotation_date');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['quotation_number', 'quotation_date', 'expiry_date', 'total_amount', 'status'];
        if ($sortBy === 'customer_name') {
            $query->join('leads', 'quotations.lead_id', '=', 'leads.id')
                  ->select('quotations.*')
                  ->orderBy(\Illuminate\Support\Facades\DB::raw('COALESCE(leads.company_name, leads.contact_person)'), $sortOrder);
        } elseif (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('quotation_date', 'desc');
        }

        $quotations = $query->paginate(10)->withQueryString();

        return view('modules.crm.quotations.approvals', compact('quotations'));
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
                ->route('crm.leads.show', ['lead' => $leadId, 'view_quotation' => 1])
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

        $this->authorize('view', $quotation);

        return view('modules.crm.quotations.show', [
            'quotation' => $quotation,
        ]);
    }

    public function downloadPdf(int $id)
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->authorize('view', $quotation);

        $pdf = Pdf::loadView('modules.crm.quotations.pdf', [
            'quotation' => $quotation,
        ]);

        return $pdf->download("Quotation_{$quotation->quotation_number}.pdf");
    }

    public function edit(int $id)
    {
        abort(404);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

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
            // Cannot change status to Sent or Accepted directly unless it has been Approved
            if (in_array($newStatus, ['Quotation Sent', 'Accepted']) && !in_array($quotation->status, ['Approved', 'Quotation Sent', 'Accepted'])) {
                return back()->withErrors(['status' => 'A quotation must be Approved before it can be Sent or Accepted.'])->withInput();
            }
            // Cannot change status to Approved directly via form (must use approve action)
            if ($newStatus === 'Approved' && $quotation->status !== 'Approved') {
                return back()->withErrors(['status' => 'Quotation approval must be performed using the Approve button.'])->withInput();
            }
        }

        // Preserve lead_id — keep existing lead_id if not passed in form
        if ($request->filled('lead_id')) {
            $validated['lead_id'] = (int) $request->input('lead_id');
        } elseif ($quotation->lead_id) {
            $validated['lead_id'] = $quotation->lead_id;
        }

        $quotation = $this->quotations->update($quotation, $validated, $request->input('items'));

        $leadId = $validated['lead_id'] ?? null;
        $this->handleQuotationStatusChange($quotation, $validated['status'], $leadId);

        if ($leadId) {
            return redirect()
                ->route('crm.leads.show', ['lead' => $leadId, 'view_quotation' => 1])
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
            
            if (!$customer) {
                $lead = null;
                if ($leadId) {
                    $lead = Lead::find($leadId);
                }
                if (!$lead && $quotation->lead_id) {
                    $lead = Lead::find($quotation->lead_id);
                }

                if ($lead) {
                    if ($lead->email) {
                        $customer = Customer::where('email', $lead->email)->first();
                    }
                    if (!$customer && $lead->phone) {
                        $customer = Customer::where('phone', $lead->phone)->first();
                    }

                    if (!$customer) {
                        Customer::create([
                            'tenant_id' => $lead->tenant_id,
                            'name' => $lead->company_name ?: ($lead->contact_person ?: 'Converted Lead'),
                            'email' => $lead->email,
                            'phone' => $lead->phone,
                            'status' => 'active',
                        ]);
                    } else {
                        $customer->update(['status' => 'active']);
                    }
                }
            } else {
                $customer->update(['status' => 'active']);
            }
        }
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->authorize('update', $quotation);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Draft,Pending Approval,Approved,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework'],
        ]);

        $newStatus = $validated['status'];
        if ($newStatus !== $quotation->status) {
            // Cannot change status to Sent or Accepted directly unless it has been Approved
            if (in_array($newStatus, ['Quotation Sent', 'Accepted']) && !in_array($quotation->status, ['Approved', 'Quotation Sent', 'Accepted'])) {
                return back()->withErrors(['status' => 'A quotation must be Approved before it can be Sent or Accepted.']);
            }
            // Cannot change status to Approved directly via form (must use approve action)
            if ($newStatus === 'Approved' && $quotation->status !== 'Approved') {
                return back()->withErrors(['status' => 'Quotation approval must be performed using the Approve button.']);
            }
        }

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => $newStatus,
        ]);

        if ($quotation->lead_id && $oldStatus !== $newStatus) {
            $lead = Lead::find($quotation->lead_id);
            if ($lead) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $lead,
                    'quotation_status_changed',
                    $oldStatus,
                    $newStatus,
                    "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to '{$newStatus}'"
                );
            }
        }

        $this->handleQuotationStatusChange($quotation, $newStatus, $quotation->lead_id);

        return back()->with('success', 'Quotation status updated successfully!');
    }

    public function approve(int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->authorize('approve', $quotation);

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'Approved',
        ]);

        if ($quotation->lead_id && $oldStatus !== 'Approved') {
            $lead = Lead::find($quotation->lead_id);
            if ($lead) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $lead,
                    'quotation_status_changed',
                    $oldStatus,
                    'Approved',
                    "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to 'Approved'"
                );
            }
        }

        $this->handleQuotationStatusChange($quotation, 'Approved', $quotation->lead_id);

        return back()->with('success', 'Quotation approved successfully!');
    }

    public function reject(int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->authorize('approve', $quotation);

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'Rejected',
        ]);

        if ($quotation->lead_id && $oldStatus !== 'Rejected') {
            $lead = Lead::find($quotation->lead_id);
            if ($lead) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $lead,
                    'quotation_status_changed',
                    $oldStatus,
                    'Rejected',
                    "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to 'Rejected'"
                );
            }
        }

        $this->handleQuotationStatusChange($quotation, 'Rejected', $quotation->lead_id);

        return back()->with('success', 'Quotation rejected successfully!');
    }

    public function destroy(int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->authorize('delete', $quotation);

        $this->quotations->delete($quotation);

        return redirect()
            ->route('crm.quotations.index')
            ->with('success', 'Quotation successfully deleted.');
    }
}
