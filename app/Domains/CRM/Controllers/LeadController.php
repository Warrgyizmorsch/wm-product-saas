<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadDocument;
use App\Domains\CRM\Repositories\LeadRepository;
use App\Domains\CRM\Services\LeadService;
use App\Domains\CRM\Services\LeadDuplicateService;
use App\Domains\CRM\Services\QuotationService;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use App\Exports\LeadSampleExport;
use App\Exports\LeadExport;
use App\Imports\LeadImport;
use Maatwebsite\Excel\Facades\Excel;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadRepository $leadRepo,
        private readonly LeadService $leadService,
        private readonly LeadDuplicateService $duplicateService,
        private readonly QuotationService $quotationService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $filters = $request->only([
            'search', 'duplicates_only', 'priority', 'segment', 'status', 'quotation_status', 'sort_by', 'sort_order'
        ]);

        $leads = $this->leadRepo->getPaginatedLeads($filters, 15);
        $this->duplicateService->annotateDuplicates($leads->items(), $tenantId);
        $quotations = $this->quotationService->latest();

        return view('modules.crm.leads.index', compact('leads', 'quotations'));
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
        $matchedLead = $this->duplicateService->checkBothMatch(
            $tenantId, $request->input('email'), $request->input('phone'), $request->input('lead_id') ? (int)$request->input('lead_id') : null
        );

        if ($matchedLead) {
            return response()->json([
                'is_duplicate' => true,
                'lead' => $matchedLead,
                'message' => "Duplicate Lead Found! Lead #{$matchedLead->id} ({$matchedLead->company_name}) has the exact same Email AND Phone number.",
            ]);
        }

        return response()->json(['is_duplicate' => false, 'lead' => null, 'message' => 'No duplicate found.']);
    }

    public function qualify(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $this->leadRepo->qualifyLead($lead);
        return redirect()->back()->with('success', "Lead #{$lead->id} ({$lead->company_name}) successfully qualified as Genuine Lead!");
    }

    public function trackStatus()
    {
        $this->authorize('viewAny', Lead::class);
        return view('modules.crm.leads.track-status');
    }

    public function downloadSample()
    {
        $this->authorize('viewAny', Lead::class);
        return Excel::download(new LeadSampleExport, 'lead_sample.xlsx');
    }

    public function import(Request $request)
    {
        $this->authorize('create', Lead::class);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt']);

        try {
            Excel::import(new LeadImport, $request->file('file'));
            return redirect()->route('crm.leads.index')->with('success', 'Leads imported successfully!');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = array_map(fn($f) => "Row {$f->row()}: " . implode(', ', $f->errors()), $e->failures());
            return redirect()->route('crm.leads.index')->withErrors($errors);
        } catch (\Exception $e) {
            return redirect()->route('crm.leads.index')->withErrors(['file' => 'Failed to import file: ' . $e->getMessage()]);
        }
    }

    public function export()
    {
        $this->authorize('viewAny', Lead::class);
        return Excel::download(new LeadExport, 'leads_export.xlsx');
    }

    public function create()
    {
        $this->authorize('create', Lead::class);
        $lead = new Lead();
        $users = User::orderBy('name')->get();
        $products = Product::sellable()->orderBy('name')->get();
        return view('modules.crm.leads.create', compact('lead', 'users', 'products'));
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $details = $this->leadRepo->getLeadDetails($lead, request()->input('active_quotation_id'));
        $users = User::orderBy('name')->get();
        $products = Product::sellable()->orderBy('name')->get();
        $nextQuotationNumber = $this->quotationService->getNextQuotationNumber();

        return view('modules.crm.leads.show', array_merge(
            compact('lead', 'users', 'products', 'nextQuotationNumber'),
            $details
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Lead::class);
        $validated = $request->validate($this->getLeadValidationRules($request), $this->getLeadValidationMessages());

        $this->leadService->storeLead($validated, $request->input('items', []), $request->input('product_ids', []));
        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully saved to Database!');
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);
        $users = User::orderBy('name')->get();
        $products = Product::sellable()->orderBy('name')->get();
        return view('modules.crm.leads.create', compact('lead', 'users', 'products'));
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validate($this->getLeadValidationRules($request), $this->getLeadValidationMessages());

        $this->leadService->updateLead($lead, $validated, $request->input('items', []), $request->input('product_ids', []));
        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully updated in Database!');
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validate(['status' => 'required|string|in:New,Follow-up Scheduled,Contacted,Qualified,Converted,Lost']);

        $res = $this->leadService->updateLeadStatus($lead, $validated['status']);
        if (!$res['success']) {
            return redirect()->back()->withErrors(['status' => $res['message']]);
        }

        return redirect()->back()->with('success', $res['message']);
    }

    public function updateOwner(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validate(['lead_owner_id' => 'nullable|exists:users,id']);
        $this->leadRepo->updateOwner($lead, $validated['lead_owner_id'] ?? null);

        return redirect()->back()->with('success', 'Lead owner successfully updated!');
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $this->leadService->deleteLead($lead);
        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully deleted from Database!');
    }

    public function uploadDocuments(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $request->validate(['documents' => 'required', 'documents.*' => 'file|max:10240']);
        $this->leadService->uploadDocuments($lead, $request->file('documents'));

        return redirect()->back()->with('success', 'Lead documents uploaded successfully!');
    }

    public function viewDocument(LeadDocument $document)
    {
        if (!$document->lead || !Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }
        return response()->file(Storage::disk('public')->path($document->file_path));
    }

    public function downloadDocument(LeadDocument $document)
    {
        if (!$document->lead || !Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }
        return response()->download(Storage::disk('public')->path($document->file_path), $document->file_name);
    }

    public function deleteDocument(LeadDocument $document)
    {
        if ($document->lead) {
            $this->authorize('update', $document->lead);
            $this->leadService->deleteDocument($document);
        }
        return redirect()->back()->with('success', 'Lead document removed successfully.');
    }

    public function convertToQuotation(Lead $lead)
    {
        $this->authorize('update', $lead);
        return redirect()->route('crm.leads.show', ['lead' => $lead->id, 'create_quotation' => 1]);
    }

    private function getLeadValidationRules(Request $request): array
    {
        $rules = [
            'lead_owner_id' => 'nullable|exists:users,id',
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'requirement' => 'nullable|string',
            'expected_amount' => 'nullable|numeric|min:0',
            'expected_sale_date' => 'nullable|date',
            'source' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'segment' => 'nullable|string|max:255',
            'industry_type' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'nullable',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:1',
        ];

        if ($request->has('call_date')) {
            $rules['call_date'] = 'required|string';
        } else {
            $rules['call_date_date'] = 'required|date';
            $rules['call_date_hour'] = 'required|string|max:2';
            $rules['call_date_minute'] = 'required|string|max:2';
            $rules['call_date_ampm'] = 'required|string|in:AM,PM';
        }

        return $rules;
    }

    private function getLeadValidationMessages(): array
    {
        return [
            'items.*.product_id.required' => 'Please select a valid Product from the dropdown for each item line.',
            'items.*.product_id.exists'   => 'The selected product does not exist in inventory.',
            'items.*.quantity.min' => 'Minimum quantity 1 is required.',
            'items.*.quantity.numeric' => 'Minimum quantity 1 is required.',
        ];
    }
}
