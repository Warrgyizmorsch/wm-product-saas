<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadDocument;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
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
            'search', 'duplicates_only', 'priority', 'segment', 'status', 'lead_owner_id', 'quotation_status', 'start_date', 'end_date', 'date_from', 'date_to', 'sort_by', 'sort_order'
        ]);

        $leads = $this->leadRepo->getPaginatedLeads($filters, 10);
        $this->duplicateService->annotateDuplicates($leads->items(), $tenantId);

        if ($request->input('duplicates_only') === '1') {
            $filtered = array_values(array_filter($leads->items(), function ($lead) {
                return !empty($lead->is_duplicate) || !empty($lead->is_original);
            }));

            usort($filtered, function ($a, $b) {
                $groupA = $a->is_duplicate ? $a->duplicate_of_id : $a->id;
                $groupB = $b->is_duplicate ? $b->duplicate_of_id : $b->id;
                if ($groupA === $groupB) {
                    return $a->id <=> $b->id;
                }
                return $groupA <=> $groupB;
            });

            $leads->setCollection(collect($filtered));
        }

        $quotations = $this->quotationService->latest();
        $users = \App\Models\User::orderBy('name')->get();

        $leadStatuses = \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses($tenantId);

        $statusCounts = Lead::query()
            ->where('tenant_id', $tenantId)
            ->select('status', \DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        $totalLeadsCount = Lead::query()->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();

        $allTenantLeads = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select(['id', 'lead_number', 'gstin', 'email', 'company_email', 'phone', 'company_phone'])
            ->get();
        $this->duplicateService->annotateDuplicates($allTenantLeads, $tenantId);
        $duplicatesCount = $allTenantLeads->filter(fn($l) => $l->is_duplicate || $l->is_original)->count();

        return view('modules.crm.leads.index', compact('leads', 'quotations', 'statusCounts', 'totalLeadsCount', 'duplicatesCount', 'users', 'leadStatuses'));
    }

    public function kanban(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $leadStatuses = \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses($tenantId);
        $statuses = $leadStatuses->pluck('name')->toArray();

        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->with(['owner', 'quotations']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        $startDate = $request->input('start_date') ?: $request->input('date_from');
        if (!empty($startDate)) {
            $query->where(function ($q) use ($startDate) {
                $q->whereDate('call_date', '>=', $startDate)
                  ->orWhereDate('created_at', '>=', $startDate);
            });
        }

        $endDate = $request->input('end_date') ?: $request->input('date_to');
        if (!empty($endDate)) {
            $query->where(function ($q) use ($endDate) {
                $q->whereDate('call_date', '<=', $endDate)
                  ->orWhereDate('created_at', '<=', $endDate);
            });
        }

        $allLeads = $query->orderBy('updated_at', 'desc')->get();

        $kanbanData = [];
        foreach ($statuses as $status) {
            $leadsInStatus = $allLeads->filter(fn($l) => ($l->status ?: 'New') === $status);
            $totalAmount = $leadsInStatus->sum(function($l) {
                return (float) ($l->expected_amount ?: ($l->quotations->last()?->total_amount ?: 0));
            });

            $kanbanData[$status] = [
                'leads' => $leadsInStatus,
                'count' => $leadsInStatus->count(),
                'total_amount' => $totalAmount,
            ];
        }

        return view('modules.crm.leads.kanban', compact('kanbanData', 'statuses'));
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $tenantId  = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
        $rawGstin  = trim((string)$request->input('gstin', ''));
        $rawPhone  = trim((string)$request->input('phone', ''));
        $rawEmail  = strtolower(trim((string)$request->input('email', '')));
        $company   = trim((string)$request->input('company_name', ''));
        $excludeLeadId = $request->input('lead_id') ? (int)$request->input('lead_id') : null;

        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

        $account = null;
        $matchedBy = null;

        // 1. Check CrmAccount by GSTIN
        if (!empty($rawGstin)) {
            $account = CrmAccount::where('tenant_id', $tenantId)->where('gstin', $rawGstin)->first();
            if ($account) $matchedBy = 'GSTIN (' . $rawGstin . ')';
        }

        // 2. Check CrmAccount by Phone (cleaned & raw)
        if (!$account && (!empty($cleanPhone) || !empty($rawPhone))) {
            $account = CrmAccount::where('tenant_id', $tenantId)
                ->where(function ($q) use ($cleanPhone, $rawPhone) {
                    if (!empty($rawPhone)) {
                        $q->where('phone', 'like', "%{$rawPhone}%");
                    }
                    if (!empty($cleanPhone) && strlen($cleanPhone) >= 5) {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"]);
                    }
                    $q->orWhereHas('contacts', function ($cq) use ($cleanPhone, $rawPhone) {
                        if (!empty($rawPhone)) {
                            $cq->where('phone', 'like', "%{$rawPhone}%")->orWhere('mobile', 'like', "%{$rawPhone}%");
                        }
                        if (!empty($cleanPhone) && strlen($cleanPhone) >= 5) {
                            $cq->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"])
                               ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"]);
                        }
                    });
                })->first();
            if ($account) $matchedBy = 'Phone Number (' . ($rawPhone ?: $cleanPhone) . ')';
        }

        // 3. Check CrmAccount by Email
        if (!$account && !empty($rawEmail)) {
            $account = CrmAccount::where('tenant_id', $tenantId)
                ->where(function ($q) use ($rawEmail) {
                    $q->whereRaw('LOWER(email) = ?', [$rawEmail])
                      ->orWhereHas('contacts', fn($cq) => $cq->whereRaw('LOWER(email) = ?', [$rawEmail]));
                })->first();
            if ($account) $matchedBy = 'Email Address (' . $rawEmail . ')';
        }

        // 4. Check CrmAccount by Company Name
        if (!$account && !empty($company) && strlen($company) >= 3) {
            $cleanCompany = trim(preg_replace('/(pvt|ltd|private|limited|inc|corp|co)/i', '', $company));
            if (!empty($cleanCompany)) {
                $account = CrmAccount::where('tenant_id', $tenantId)
                    ->where('name', 'like', "%{$cleanCompany}%")
                    ->first();
                if ($account) $matchedBy = 'Company Name (' . $account->name . ')';
            }
        }

        // 5. If no CrmAccount found, check Customer Master (`customers` table)
        if (!$account) {
            $custMatch = null;
            $custMatchedBy = null;

            if (!empty($rawGstin)) {
                $custMatch = \App\Domains\CRM\Models\Customer::where('tenant_id', $tenantId)->where('gstin', $rawGstin)->first();
                if ($custMatch) $custMatchedBy = 'GSTIN (' . $rawGstin . ')';
            }

            if (!$custMatch && !empty($rawEmail)) {
                $custMatch = \App\Domains\CRM\Models\Customer::where('tenant_id', $tenantId)->whereRaw('LOWER(email) = ?', [$rawEmail])->first();
                if ($custMatch) $custMatchedBy = 'Customer Email (' . $rawEmail . ')';
            }

            if (!$custMatch && (!empty($cleanPhone) || !empty($rawPhone))) {
                $custMatch = \App\Domains\CRM\Models\Customer::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($cleanPhone, $rawPhone) {
                        if (!empty($rawPhone)) $q->where('phone', 'like', "%{$rawPhone}%");
                        if (!empty($cleanPhone) && strlen($cleanPhone) >= 5) {
                            $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"]);
                        }
                    })->first();
                if ($custMatch) $custMatchedBy = 'Customer Phone (' . ($rawPhone ?: $cleanPhone) . ')';
            }

            if (!$custMatch && !empty($company) && strlen($company) >= 3) {
                $custMatch = \App\Domains\CRM\Models\Customer::where('tenant_id', $tenantId)->where('name', 'like', "%{$company}%")->first();
                if ($custMatch) $custMatchedBy = 'Customer Name (' . $custMatch->name . ')';
            }

            if ($custMatch) {
                // Auto sync CrmAccount for this customer
                $account = CrmAccount::where('customer_id', $custMatch->id)->first();
                if (!$account) {
                    $account = CrmAccount::create([
                        'tenant_id'   => $tenantId,
                        'customer_id' => $custMatch->id,
                        'name'        => $custMatch->name,
                        'email'       => $custMatch->email,
                        'phone'       => $custMatch->phone,
                        'status'      => 'active',
                        'owner_id'    => auth()->id(),
                    ]);
                }
                $matchedBy = $custMatchedBy;
            }
        }

        if ($account) {
            $account->load(['contacts', 'deals']);
            return response()->json([
                'matched'            => true,
                'matched_by'         => $matchedBy,
                'match_key'          => 'account_' . $account->id . '_' . md5($matchedBy),
                'account_id'         => $account->id,
                'account_name'       => $account->name,
                'account_number'     => $account->account_number,
                'gstin'              => $account->gstin ?: 'N/A',
                'email'              => $account->email ?: 'N/A',
                'phone'              => $account->phone ?: 'N/A',
                'lifetime_revenue'   => number_format($account->lifetime_revenue, 2),
                'open_deals_count'   => $account->open_deals_count,
                'last_purchase_date' => $account->last_purchase_date ? $account->last_purchase_date->format('Y-m-d') : 'No purchases yet',
                'primary_contact'    => $account->primaryContact ? $account->primaryContact->name : ($account->contacts->first() ? $account->contacts->first()->name : 'N/A'),
            ]);
        }

        // 6. Check Lead-to-Lead match in `leads` table
        $leadQuery = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($excludeLeadId) {
            $leadQuery->where('id', '!=', $excludeLeadId);
        }

        $matchedLead = null;
        $leadMatchedBy = null;

        if (!empty($rawGstin)) {
            $matchedLead = (clone $leadQuery)->where('gstin', $rawGstin)->first();
            if ($matchedLead) $leadMatchedBy = 'GSTIN (' . $rawGstin . ')';
        }

        if (!$matchedLead && !empty($rawEmail)) {
            $matchedLead = (clone $leadQuery)
                ->where(function ($q) use ($rawEmail) {
                    $q->whereRaw('LOWER(email) = ?', [$rawEmail])
                      ->orWhereRaw('LOWER(company_email) = ?', [$rawEmail]);
                })->first();
            if ($matchedLead) $leadMatchedBy = 'Lead Email (' . $rawEmail . ')';
        }

        if (!$matchedLead && (!empty($cleanPhone) || !empty($rawPhone))) {
            $matchedLead = (clone $leadQuery)
                ->where(function ($q) use ($cleanPhone, $rawPhone) {
                    if (!empty($rawPhone)) {
                        $q->where('phone', 'like', "%{$rawPhone}%")
                          ->orWhere('company_phone', 'like', "%{$rawPhone}%");
                    }
                    if (!empty($cleanPhone) && strlen($cleanPhone) >= 5) {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"])
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(company_phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"]);
                    }
                })->first();
            if ($matchedLead) $leadMatchedBy = 'Lead Phone (' . ($rawPhone ?: $cleanPhone) . ')';
        }

        if (!$matchedLead && !empty($company) && strlen($company) >= 3) {
            $cleanCompany = trim(preg_replace('/(pvt|ltd|private|limited|inc|corp|co)/i', '', $company));
            $matchedLead = (clone $leadQuery)
                ->where(function ($q) use ($company, $cleanCompany) {
                    $q->where('company_name', 'like', "%{$company}%");
                    if (!empty($cleanCompany)) {
                        $q->orWhere('company_name', 'like', "%{$cleanCompany}%");
                    }
                    $q->orWhere('contact_person', 'like', "%{$company}%");
                })->first();
            if ($matchedLead) {
                $matchedName = $matchedLead->company_name ?: $matchedLead->contact_person;
                $leadMatchedBy = 'Company / Lead Name (' . $matchedName . ')';
            }
        }

        if ($matchedLead) {
            $displayName = $matchedLead->company_name ?: $matchedLead->contact_person ?: "Lead #{$matchedLead->id}";
            return response()->json([
                'matched'      => false,
                'is_duplicate' => true,
                'matched_by'   => $leadMatchedBy,
                'match_key'    => 'lead_' . $matchedLead->id . '_' . md5($leadMatchedBy),
                'lead'         => $matchedLead,
                'message'      => "Duplicate Lead Found! Lead #{$matchedLead->id} ({$displayName}) has matching " . $leadMatchedBy,
            ]);
        }

        return response()->json(['matched' => false, 'is_duplicate' => false]);
    }

    public function qualify(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $this->leadRepo->qualifyLead($lead);
        return redirect()->back()->with('success', "Lead #{$lead->id} successfully converted to Account & Deal and status updated to Won!");
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
        $products = Product::sellable()->with('parent')->orderBy('name')->get();
        $leadStatuses = \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses();
        return view('modules.crm.leads.create', compact('lead', 'users', 'products', 'leadStatuses'));
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $details = $this->leadRepo->getLeadDetails($lead, request()->input('active_quotation_id'));
        $users = User::orderBy('name')->get();
        $products = Product::sellable()->with('parent')->orderBy('name')->get();
        $nextQuotationNumber = $this->quotationService->getNextQuotationNumber();
        $leadStatuses = \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses();

        return view('modules.crm.leads.show', array_merge(
            compact('lead', 'users', 'products', 'nextQuotationNumber', 'leadStatuses'),
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
        $products = Product::sellable()->with('parent')->orderBy('name')->get();
        $leadStatuses = \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses();
        return view('modules.crm.leads.create', compact('lead', 'users', 'products', 'leadStatuses'));
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
        $dbStatuses = \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses()->pluck('name')->toArray();
        $allowedStatuses = implode(',', array_unique(array_merge(['New', 'Qualified', 'Won', 'Lost'], $dbStatuses)));
        $validated = $request->validate(['status' => 'required|string|in:' . $allowedStatuses]);

        $res = $this->leadService->updateLeadStatus($lead, $validated['status']);

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            if (!$res['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $res['message']
                ], 422);
            }
            return response()->json([
                'success' => true,
                'message' => $res['message']
            ]);
        }

        if (!$res['success']) {
            return redirect()->back()->withErrors(['status' => $res['message']])->with('error', $res['message']);
        }

        return redirect()->back()->with('success', $res['message']);
    }

    public function updatePriority(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validate([
            'priority' => 'required|string|in:Low,Medium,High,Urgent'
        ]);

        $lead->update(['priority' => $validated['priority']]);

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => "Priority updated to {$lead->priority}!",
                'priority' => $lead->priority,
                'lead_id' => $lead->id
            ]);
        }

        return redirect()->back()->with('success', "Priority updated to {$lead->priority}!");
    }

    public function updateOwner(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validate(['lead_owner_id' => 'nullable|exists:users,id']);
        $this->leadRepo->updateOwner($lead, $validated['lead_owner_id'] ?? null);

        return redirect()->back()->with('success', 'Lead owner successfully updated!');
    }

    public function updateRequirement(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validate([
            'requirement' => 'nullable|string',
        ]);

        $lead->requirement = $validated['requirement'] ?? null;
        $lead->save();

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => 'Requirements updated successfully!',
                'requirement' => $lead->requirement
            ]);
        }

        return redirect()->back()->with('success', 'Lead requirements updated successfully!');
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
        $isB2B = $request->input('lead_type', 'b2b') === 'b2b';

        $rules = [
            'lead_owner_id' => 'nullable|exists:users,id',
            'company_name' => $isB2B ? 'required|string|max:255' : 'nullable|string|max:255',
            'company_email' => $isB2B ? 'required|email|max:255' : 'nullable|email|max:255',
            'company_phone' => 'nullable|string|regex:/^[0-9]+$/',
            'gstin' => 'nullable|string|max:100',
            'lead_type' => 'nullable|string|in:b2b,b2c',
            'contact_person' => $isB2B ? 'nullable|string|max:255' : 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'email' => $isB2B ? 'nullable|email|max:255' : 'required|email|max:255',
            'phone' => 'nullable|string|regex:/^[0-9]+$/',
            'additional_contacts' => 'nullable|array',
            'additional_contacts.*.name' => 'nullable|string|max:255',
            'additional_contacts.*.designation' => 'nullable|string|max:255',
            'additional_contacts.*.email' => 'nullable|email|max:255',
            'additional_contacts.*.phone' => 'nullable|string|regex:/^[0-9]+$/',
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
            'items.*.product_id' => 'nullable|integer|exists:products,id',
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
            'company_name.required' => 'Company Name is required for B2B (Business Client) leads.',
            'company_email.required' => 'Company Email is required for B2B (Business Client) leads.',
            'company_email.email' => 'Please enter a valid email address for Company Email.',
            'contact_person.required' => 'Contact Person is required for B2C (Individual Customer) leads.',
            'email.required' => 'Contact Email is required for B2C (Individual Customer) leads.',
            'email.email' => 'Please enter a valid email address for Contact Email.',
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'additional_contact_phone.regex' => 'Additional contact phone number must contain digits/numbers only (no special characters or letters).',
            'additional_contacts.*.phone.regex' => 'Additional contact phone number must contain digits/numbers only (no special characters or letters).',
            'items.*.product_id.required' => 'Please select a valid Product from the dropdown for each item line.',
            'items.*.product_id.exists'   => 'The selected product does not exist in inventory.',
            'items.*.quantity.min' => 'Minimum quantity 1 is required.',
            'items.*.quantity.numeric' => 'Minimum quantity 1 is required.',
        ];
    }
}
