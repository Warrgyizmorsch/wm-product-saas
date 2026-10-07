<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadStatus;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\DealStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LeadApiController
 * 
 * Location: app/Domains/CRM/Controllers/Api/LeadApiController.php (CRM Module)
 * 
 * Enterprise-Grade Lead Export & Import REST API Kit.
 * Endpoints:
 * - GET  /api/crm/leads/meta    (Master dropdowns for Lead UI)
 * - GET  /api/crm/leads/export  (Full Big-ERP Export API with advanced filters)
 * - GET  /api/crm/leads         (Alias to Export / List API)
 * - POST /api/crm/leads         (Single & Bulk Lead Import / Sync API)
 * 
 * Authentication: Laravel Sanctum (Bearer Token)
 * Authorization: LeadPolicy (crm.leads.view, crm.leads.create)
 */
class LeadApiController extends Controller
{
    /**
     * Resolve Tenant, Company, and Branch Context
     */
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/crm/leads/meta
     */
    public function meta(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $statuses = LeadStatus::where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'is_protected', 'sort_order']);

        $owners = User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'   => $statuses,
                'priorities' => ['low', 'medium', 'high', 'urgent'],
                'sources'    => ['Website', 'Referral', 'Cold Call', 'WhatsApp', 'Exhibition', 'Partner', 'Inbound', 'Other'],
                'lead_types' => ['Individual', 'Corporate', 'Government', 'Dealer/Distributor'],
                'owners'     => $owners,
            ],
        ]);
    }

    /**
     * GET /api/crm/leads/check-duplicate
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $email = trim((string)$request->input('email'));
        $phone = trim((string)$request->input('phone'));
        $excludeId = $request->input('exclude_id');

        if (empty($email) && empty($phone)) {
            return response()->json([
                'success' => true,
                'is_duplicate' => false,
                'message' => 'Please provide email or phone to check.',
            ]);
        }

        $query = Lead::where('tenant_id', $tenantId);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->where('email', $email)->orWhere('company_email', $email);
            }
            if ($phone) {
                $q->orWhere('phone', $phone)->orWhere('company_phone', $phone);
            }
        });

        $duplicates = $query->get(['id', 'lead_number', 'contact_person', 'company_name', 'email', 'phone', 'status', 'created_at']);

        return response()->json([
            'success'      => true,
            'is_duplicate' => $duplicates->isNotEmpty(),
            'count'        => $duplicates->count(),
            'duplicates'   => $duplicates,
        ]);
    }

    /**
     * GET /api/crm/leads
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->with(['owner:id,name,email', 'crmAccount:id,name', 'crmDeal:id,title,estimated_value']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('contact_person', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('lead_number', 'like', "%{$search}%");
            });
        }

        // Status Filter (status_id or status name)
        if ($statusId = $request->input('status_id')) {
            $statusObj = LeadStatus::find($statusId);
            if ($statusObj) {
                $query->where('status', $statusObj->name);
            }
        } elseif ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Priority Filter
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Owner Filter
        if ($ownerId = $request->input('owner_id')) {
            $query->where('lead_owner_id', $ownerId);
        }

        // Date Filters
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Has Deal / Converted Filter
        if ($request->has('has_deal')) {
            if ($request->boolean('has_deal')) {
                $query->whereNotNull('crm_deal_id');
            } else {
                $query->whereNull('crm_deal_id');
            }
        }

        // Sorting
        $sortBy  = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'lead_number', 'contact_person', 'company_name', 'expected_amount', 'created_at', 'priority', 'status'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $leads   = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $leads->items(),
            'meta'    => [
                'current_page' => $leads->currentPage(),
                'last_page'    => $leads->lastPage(),
                'per_page'     => $leads->perPage(),
                'total'        => $leads->total(),
            ],
        ]);
    }

    /**
     * GET /api/crm/leads/export
     * 
     * Full Database Lead Export API Kit for 3rd-party ERPs / CRM integrations.
     * Outputs all lead records with structured entity objects + complete database flat fields.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        [$tenantId] = $this->resolveTenantContext();

        $query = Lead::query()
            ->where('tenant_id', $tenantId)
            ->with(['owner', 'crmAccount', 'crmContact', 'crmDeal']);

        // 1. Filter: Status (single or comma-separated e.g. "New,Contacted,Proposal,Won,Lost")
        if ($request->filled('status')) {
            $statuses = is_array($request->input('status'))
                ? $request->input('status')
                : explode(',', (string) $request->input('status'));
            $query->whereIn('status', array_map('trim', $statuses));
        }

        // 2. Filter: Lead Type (b2b, b2c)
        if ($request->filled('lead_type')) {
            $query->where('lead_type', strtolower(trim((string) $request->input('lead_type'))));
        }

        // 3. Filter: Priority (Low, Medium, High)
        if ($request->filled('priority')) {
            $priorities = is_array($request->input('priority'))
                ? $request->input('priority')
                : explode(',', (string) $request->input('priority'));
            $query->whereIn('priority', array_map('trim', $priorities));
        }

        // 4. Filter: Source (e.g. Website, Referral, Cold Call, etc.)
        if ($request->filled('source')) {
            $sources = is_array($request->input('source'))
                ? $request->input('source')
                : explode(',', (string) $request->input('source'));
            $query->whereIn('source', array_map('trim', $sources));
        }

        // 5. Filter: Segment
        if ($request->filled('segment')) {
            $query->where('segment', trim((string) $request->input('segment')));
        }

        // 6. Filter: Industry Type
        if ($request->filled('industry_type')) {
            $query->where('industry_type', trim((string) $request->input('industry_type')));
        }

        // 7. Filter: Lead Owner ID / Assigned Sales Rep
        if ($request->filled('lead_owner_id')) {
            $query->where('lead_owner_id', (int) $request->input('lead_owner_id'));
        }

        // 8. Filter: Location (Country, State, City)
        if ($request->filled('country')) {
            $query->where('country', trim((string) $request->input('country')));
        }
        if ($request->filled('state')) {
            $query->where('state', trim((string) $request->input('state')));
        }
        if ($request->filled('city')) {
            $query->where('city', trim((string) $request->input('city')));
        }

        // 9. Filter: Converted to Customer (is_customer)
        if ($request->has('is_customer')) {
            $isCust = filter_var($request->input('is_customer'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isCust !== null) {
                $query->where('is_customer', $isCust);
            }
        }

        // 10. Filter: Date Range (by created_at, call_date, or expected_sale_date)
        $dateColumn = in_array($request->input('date_filter_by'), ['call_date', 'expected_sale_date', 'updated_at'], true)
            ? $request->input('date_filter_by')
            : 'created_at';

        if ($request->filled('from_date')) {
            try {
                $query->whereDate($dateColumn, '>=', Carbon::parse($request->input('from_date')));
            } catch (\Throwable $e) {
                // Ignore malformed date
            }
        }
        if ($request->filled('to_date')) {
            try {
                $query->whereDate($dateColumn, '<=', Carbon::parse($request->input('to_date')));
            } catch (\Throwable $e) {
                // Ignore malformed date
            }
        }

        // 11. Search: Keyword across key identifiers
        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('lead_number', 'like', $search)
                  ->orWhere('company_name', 'like', $search)
                  ->orWhere('company_email', 'like', $search)
                  ->orWhere('company_phone', 'like', $search)
                  ->orWhere('contact_person', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhere('gstin', 'like', $search)
                  ->orWhere('city', 'like', $search)
                  ->orWhere('requirement', 'like', $search);
            });
        }

        // 12. Sorting
        $sortBy = in_array($request->input('sort_by'), ['created_at', 'id', 'lead_number', 'company_name', 'expected_amount', 'call_date', 'expected_sale_date', 'status'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 13. Pagination / Limit Handling (Supports both limit and per_page, plus page number)
        $totalCount = (clone $query)->count();
        $limit = $request->filled('limit')
            ? max((int)$request->input('limit'), 1)
            : ($request->filled('per_page') ? max((int)$request->input('per_page'), 1) : null);

        $page = $request->filled('page') ? max((int)$request->input('page'), 1) : 1;

        if ($limit !== null) {
            $query->forPage($page, $limit);
        }

        $leads = $query->get();

        // 14. Transform records into enterprise export format
        $exportedLeads = $leads->map(function (Lead $lead) {
            return [
                // Primary Identifiers
                'id'                   => $lead->id,
                'lead_number'          => $lead->lead_number,
                'lead_type'            => $lead->lead_type ?? 'b2b',
                'status'               => $lead->status ?? 'New',
                'priority'             => $lead->priority,
                'source'               => $lead->source,
                'segment'              => $lead->segment,
                'industry_type'        => $lead->industry_type,

                // Company Details (B2B)
                'company' => [
                    'name'             => $lead->company_name,
                    'email'            => $lead->company_email,
                    'phone'            => $lead->company_phone,
                    'gstin'            => $lead->gstin,
                ],

                // Primary Contact Person Details
                'contact' => [
                    'person_name'      => $lead->contact_person,
                    'designation'      => $lead->designation,
                    'email'            => $lead->email,
                    'phone'            => $lead->phone,
                ],

                // Assigned Owner / Sales Rep
                'lead_owner' => [
                    'id'               => $lead->owner?->id ?? $lead->lead_owner_id,
                    'name'             => $lead->owner?->name,
                    'email'            => $lead->owner?->email,
                ],

                // Financials & Deal Estimation
                'financials' => [
                    'expected_amount'    => $lead->expected_amount !== null ? (float)$lead->expected_amount : null,
                    'expected_sale_date' => $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : null,
                ],

                // Location & Address
                'location' => [
                    'address'          => $lead->address,
                    'city'             => $lead->city,
                    'state'            => $lead->state,
                    'country'          => $lead->country,
                ],

                // Requirement & Note
                'requirement'          => $lead->requirement,

                // Products of Interest
                'products' => [
                    'product_ids'      => $lead->product_ids ?? [],
                    'product_names'    => $lead->product_names,
                    'product_items'    => $lead->product_items ?? [],
                ],

                // Additional Multi-Contact Repeater
                'additional_contacts'  => $lead->additional_contacts ?? [],

                // Digital Marketing & Attribution Tracking
                'marketing_attribution' => [
                    'utm_source'       => $lead->utm_source,
                    'utm_medium'       => $lead->utm_medium,
                    'utm_campaign'     => $lead->utm_campaign,
                    'utm_term'         => $lead->utm_term,
                    'utm_content'      => $lead->utm_content,
                ],

                // Conversion & CRM Linkage
                'crm_conversion' => [
                    'is_customer'      => (bool)$lead->is_customer,
                    'crm_account_id'   => $lead->crm_account_id,
                    'crm_account_name' => $lead->crmAccount?->name,
                    'crm_contact_id'   => $lead->crm_contact_id,
                    'crm_deal_id'      => $lead->crm_deal_id,
                    'crm_deal_title'   => $lead->crmDeal?->title,
                    'converted_at'     => $lead->converted_at ? Carbon::parse($lead->converted_at)->toIso8601String() : null,
                ],

                // Timeline Dates
                'timeline' => [
                    'call_date'          => $lead->call_date ? $lead->call_date->toIso8601String() : null,
                    'next_followup_date' => $lead->next_followup_date ? $lead->next_followup_date->toIso8601String() : null,
                    'created_at'         => $lead->created_at ? $lead->created_at->toIso8601String() : null,
                    'updated_at'         => $lead->updated_at ? $lead->updated_at->toIso8601String() : null,
                ],
            ];
        });

        $totalPages = $limit ? (int)ceil($totalCount / $limit) : 1;

        return response()->json([
            'success'          => true,
            'api_version'      => 'v1',
            'exported_at'      => now()->toIso8601String(),
            'total_records'    => $totalCount,
            'count'            => $exportedLeads->count(),
            'current_page'     => $limit ? $page : 1,
            'per_page'         => $limit ?: $totalCount,
            'total_pages'      => $totalPages,
            'filters_applied'  => array_filter($request->only([
                'status', 'lead_type', 'priority', 'source', 'segment', 'industry_type',
                'lead_owner_id', 'country', 'state', 'city', 'is_customer', 'from_date', 'to_date', 'search', 'date_filter_by',
                'limit', 'per_page', 'page', 'sort_by', 'sort_direction'
            ])),
            'data'             => $exportedLeads,
        ], 200);
    }

    /**
     * Shared validation rules for Lead Import / Ingestion.
     * Supports both flat structures and nested Big ERP payload objects.
     */
    private function leadRules(array $data = []): array
    {
        $hasCompanyName  = !empty(trim($data['company_name'] ?? ($data['company']['name'] ?? '')));
        $hasCompanyEmail = !empty(trim($data['company_email'] ?? ($data['company']['email'] ?? '')));
        $isB2B           = $hasCompanyName || $hasCompanyEmail;

        return [
            // Identifiers
            'lead_number'        => 'nullable|string|max:100',
            'lead_type'          => 'nullable|string|in:b2b,b2c,B2B,B2C',
            'status'             => 'nullable|string|max:100',
            'priority'           => 'nullable|string|max:50',
            'source'             => 'nullable|string|max:255',
            'segment'            => 'nullable|string|max:255',
            'industry_type'      => 'nullable|string|max:255',

            // Company Details
            'company_name'       => $isB2B ? 'required|string|max:255' : 'nullable|string|max:255',
            'gstin'              => 'nullable|string|max:100',
            'company_email'      => $isB2B ? 'required|email|max:255' : 'nullable|email|max:255',
            'company_phone'      => 'nullable|string|max:50',

            // Contact Person Details
            'contact_person'     => $isB2B ? 'nullable|string|max:255' : 'required|string|max:255',
            'designation'        => 'nullable|string|max:255',
            'email'              => $isB2B ? 'nullable|email|max:255' : 'required|email|max:255',
            'phone'              => 'nullable|string|max:50',

            // Sales Rep / Owner
            'lead_owner_id'      => 'nullable|integer',
            'owner_email'        => 'nullable|email|max:255',

            // Financials & Dates
            'call_date'          => 'nullable|date',
            'expected_amount'    => 'nullable|numeric|min:0',
            'expected_sale_date' => 'nullable|date',
            'next_followup_date' => 'nullable|date',

            // Details & Location
            'requirement'        => 'nullable|string',
            'country'            => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:255',
            'address'            => 'nullable|string',

            // Marketing Attribution
            'utm_source'         => 'nullable|string|max:255',
            'utm_medium'         => 'nullable|string|max:255',
            'utm_campaign'       => 'nullable|string|max:255',
            'utm_term'           => 'nullable|string|max:255',
            'utm_content'        => 'nullable|string|max:255',

            // Products & Additional Lists
            'product_ids'                       => 'nullable|array',
            'product_ids.*'                     => 'integer',
            'product_items'                     => 'nullable|array',
            'additional_contacts'               => 'nullable|array',
            'additional_contacts.*.name'        => 'nullable|string|max:255',
            'additional_contacts.*.designation' => 'nullable|string|max:255',
            'additional_contacts.*.phone'       => 'nullable|string|max:50',
            'additional_contacts.*.email'       => 'nullable|email|max:255',
            'is_customer'                       => 'nullable|boolean',
        ];
    }

    /**
     * Custom validation error messages
     */
    private function leadValidationMessages(): array
    {
        return [
            'company_name.required'   => 'company_name is required when company_email is provided.',
            'company_email.required'  => 'company_email is required when company_name is provided.',
            'contact_person.required' => 'contact_person is required for B2C leads (when company details are not provided).',
            'email.required'          => 'email (contact email) is required for B2C leads (when company details are not provided).',
        ];
    }

    /**
     * Build Lead model instance with clean defaults & auto-mappings
     */
    private function buildLead(array $data, int $tenantId, int $companyId, ?int $branchId): Lead
    {
        // Support nested object payloads (e.g. from big ERP exports)
        $companyName  = $data['company_name']  ?? ($data['company']['name'] ?? null);
        $companyEmail = $data['company_email'] ?? ($data['company']['email'] ?? null);
        $companyPhone = $data['company_phone'] ?? ($data['company']['phone'] ?? null);
        $gstin        = $data['gstin']         ?? ($data['company']['gstin'] ?? null);

        $contactPerson = $data['contact_person'] ?? ($data['contact']['person_name'] ?? ($data['contact']['name'] ?? null));
        $designation   = $data['designation']    ?? ($data['contact']['designation'] ?? null);
        $email         = $data['email']          ?? ($data['contact']['email'] ?? null);
        $phone         = $data['phone']          ?? ($data['contact']['phone'] ?? null);

        $address = $data['address'] ?? ($data['location']['address'] ?? null);
        $city    = $data['city']    ?? ($data['location']['city'] ?? null);
        $state   = $data['state']   ?? ($data['location']['state'] ?? null);
        $country = $data['country'] ?? ($data['location']['country'] ?? null);

        $expectedAmount   = isset($data['expected_amount']) ? (float)$data['expected_amount'] : (isset($data['financials']['expected_amount']) ? (float)$data['financials']['expected_amount'] : null);
        $expectedSaleDate = $data['expected_sale_date'] ?? ($data['financials']['expected_sale_date'] ?? null);

        // Resolve Lead Type
        $leadType = !empty($data['lead_type'])
            ? strtolower($data['lead_type'])
            : (!empty(trim((string)$companyName)) ? 'b2b' : 'b2c');

        // Resolve Call Date
        $callDate = !empty($data['call_date']) ? Carbon::parse($data['call_date']) : now();

        // Resolve Expected Sale Date
        $parsedExpectedSaleDate = null;
        if (!empty($expectedSaleDate)) {
            try {
                $parsedExpectedSaleDate = Carbon::parse($expectedSaleDate);
            } catch (\Throwable $e) {
                $parsedExpectedSaleDate = null;
            }
        }

        // Resolve Next Followup Date
        $nextFollowupDate = null;
        if (!empty($data['next_followup_date'])) {
            try {
                $nextFollowupDate = Carbon::parse($data['next_followup_date']);
            } catch (\Throwable $e) {
                $nextFollowupDate = null;
            }
        }

        // Resolve Lead Owner
        $leadOwnerId = $data['lead_owner_id'] ?? ($data['lead_owner']['id'] ?? null);
        if ($leadOwnerId && !User::where('id', $leadOwnerId)->exists()) {
            $leadOwnerId = null;
        }
        if (!$leadOwnerId && !empty($data['owner_email'])) {
            $owner = User::where('tenant_id', $tenantId)->where('email', $data['owner_email'])->first();
            if ($owner) {
                $leadOwnerId = $owner->id;
            }
        }
        if (!$leadOwnerId) {
            $leadOwnerId = auth()->id() ?? User::where('tenant_id', $tenantId)->value('id') ?? User::first()?->id;
        }

        // Clean additional_contacts array
        $additionalContacts = null;
        $contactsInput = $data['additional_contacts'] ?? [];
        if (!empty($contactsInput) && is_array($contactsInput)) {
            $additionalContacts = array_values(array_filter($contactsInput, function ($contact) {
                return !empty($contact['name']) || !empty($contact['designation']) || !empty($contact['phone']) || !empty($contact['email']);
            }));
        }

        // Digital Marketing attribution
        $utmSource   = $data['utm_source']   ?? ($data['marketing_attribution']['utm_source'] ?? null);
        $utmMedium   = $data['utm_medium']   ?? ($data['marketing_attribution']['utm_medium'] ?? null);
        $utmCampaign = $data['utm_campaign'] ?? ($data['marketing_attribution']['utm_campaign'] ?? null);
        $utmTerm     = $data['utm_term']     ?? ($data['marketing_attribution']['utm_term'] ?? null);
        $utmContent  = $data['utm_content']  ?? ($data['marketing_attribution']['utm_content'] ?? null);

        // Products
        $productIds   = $data['product_ids']   ?? ($data['products']['product_ids'] ?? null);
        $productItems = $data['product_items'] ?? ($data['products']['product_items'] ?? null);

        return new Lead([
            'tenant_id'           => $tenantId,
            'company_id'          => $companyId,
            'branch_id'           => $branchId,
            'lead_number'         => !empty($data['lead_number']) ? trim((string)$data['lead_number']) : null,
            'lead_type'           => $leadType,
            'call_date'           => $callDate,
            'company_name'        => $companyName,
            'gstin'               => $gstin,
            'company_email'       => $companyEmail,
            'company_phone'       => $companyPhone,
            'contact_person'      => $contactPerson,
            'designation'         => $designation,
            'email'               => $email,
            'phone'               => $phone,
            'lead_owner_id'       => $leadOwnerId,
            'expected_amount'     => $expectedAmount,
            'expected_sale_date'  => $parsedExpectedSaleDate,
            'next_followup_date'  => $nextFollowupDate,
            'requirement'         => $data['requirement']   ?? null,
            'industry_type'       => $data['industry_type'] ?? null,
            'source'              => $data['source']        ?? null,
            'priority'            => $data['priority']      ?? null,
            'segment'             => $data['segment']       ?? null,
            'country'             => $country,
            'state'               => $state,
            'city'                => $city,
            'address'             => $address,
            'status'              => !empty($data['status']) ? trim((string)$data['status']) : 'New',
            'utm_source'          => $utmSource,
            'utm_medium'          => $utmMedium,
            'utm_campaign'        => $utmCampaign,
            'utm_term'            => $utmTerm,
            'utm_content'         => $utmContent,
            'product_ids'         => !empty($productIds) && is_array($productIds) ? $productIds : null,
            'product_items'       => !empty($productItems) && is_array($productItems) ? $productItems : null,
            'additional_contacts' => !empty($additionalContacts) ? $additionalContacts : null,
            'is_customer'         => !empty($data['is_customer']),
        ]);
    }

    /**
     * POST /api/crm/leads
     * Single & Bulk Lead Import / Ingestion API
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $payload = $request->all();

        // 1) Direct JSON array: [ {...}, {...} ]
        if (is_array($payload) && array_is_list($payload)) {
            return $this->processBulkLeads($payload);
        }

        // 2) Object with "leads" array: { "leads": [ {...}, {...} ] }
        if (isset($payload['leads']) && is_array($payload['leads'])) {
            return $this->processBulkLeads($payload['leads']);
        }

        // 3) Single Lead Object: { "company_name": "ABC Corp", ... }
        $validator = Validator::make($payload, $this->leadRules($payload), $this->leadValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed for lead.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
            $lead = $this->buildLead($validator->validated(), $tenantId, $companyId, $branchId);
            $lead->save();

            return response()->json([
                'success' => true,
                'type'    => 'single',
                'message' => 'Lead created successfully.',
                'data'    => [
                    'id'          => $lead->id,
                    'lead_number' => $lead->lead_number,
                    'lead_type'   => $lead->lead_type,
                    'company'     => $lead->company_name,
                    'contact'     => $lead->contact_person,
                    'status'      => $lead->status,
                    'created_at'  => $lead->created_at?->toIso8601String(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lead: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process bulk list of leads (up to 500 per batch)
     */
    private function processBulkLeads(array $leadsList): JsonResponse
    {
        if (count($leadsList) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 500 leads allowed per import request.',
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $created = [];
        $failed  = [];

        foreach ($leadsList as $index => $row) {
            if (!is_array($row)) {
                $failed[] = [
                    'row'    => $index + 1,
                    'errors' => ['Row must be a valid JSON object'],
                ];
                continue;
            }

            $rowValidator = Validator::make($row, $this->leadRules($row), $this->leadValidationMessages());
            if ($rowValidator->fails()) {
                $failed[] = [
                    'row'    => $index + 1,
                    'data'   => $row,
                    'errors' => $rowValidator->errors(),
                ];
                continue;
            }

            try {
                $lead = $this->buildLead($rowValidator->validated(), $tenantId, $companyId, $branchId);
                $lead->save();
                $created[] = [
                    'row'         => $index + 1,
                    'id'          => $lead->id,
                    'lead_number' => $lead->lead_number,
                    'company'     => $lead->company_name,
                    'contact'     => $lead->contact_person,
                    'status'      => $lead->status,
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'row'   => $index + 1,
                    'data'  => $row,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success'       => true,
            'type'          => 'bulk',
            'total_sent'    => count($leadsList),
            'total_created' => count($created),
            'total_failed'  => count($failed),
            'created'       => $created,
            'failed'        => $failed,
        ], 200);
    }

    /**
     * GET /api/crm/leads/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $lead = Lead::where('tenant_id', $tenantId)
            ->with(['owner', 'crmAccount', 'crmContact', 'crmDeal', 'followups'])
            ->find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => "Lead with ID {$id} not found in this tenant context.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $lead,
        ]);
    }

    /**
     * PUT/PATCH /api/crm/leads/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => "Lead with ID {$id} not found.",
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'priority'        => ['nullable', 'in:low,medium,high,urgent'],
            'status'          => ['nullable', 'string'],
            'status_id'       => ['nullable', 'integer'],
            'estimated_value' => ['nullable', 'numeric'],
            'lead_owner_id'   => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (isset($validated['status_id'])) {
            $statusObj = LeadStatus::find($validated['status_id']);
            if ($statusObj) {
                $validated['status'] = $statusObj->name;
            }
        }

        $lead->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
            'data'    => $lead->fresh()->load(['owner', 'crmAccount']),
        ]);
    }

    /**
     * PATCH /api/crm/leads/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => "Lead with ID {$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'status_id' => ['nullable', 'integer'],
            'status'    => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $newStatus = null;
        if ($statusId = $request->input('status_id')) {
            $statusObj = LeadStatus::find($statusId);
            if ($statusObj) {
                $newStatus = $statusObj->name;
            }
        } elseif ($status = $request->input('status')) {
            $newStatus = $status;
        }

        if (!$newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide status or status_id.',
            ], 422);
        }

        $leadService = app(\App\Domains\CRM\Services\LeadService::class);
        $result = $leadService->updateLeadStatus($lead, $newStatus);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $lead->fresh()->load(['owner', 'crmAccount', 'crmDeal']),
        ]);
    }

    /**
     * PATCH /api/crm/leads/{id}/priority
     */
    public function updatePriority(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => "Lead with ID {$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'priority' => ['required', 'string', 'in:low,medium,high,urgent,Low,Medium,High,Urgent'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $lead->priority = ucfirst(strtolower($request->input('priority')));
        $lead->save();

        return response()->json([
            'success' => true,
            'message' => "Lead priority updated to {$lead->priority}",
            'data'    => $lead,
        ]);
    }

    /**
     * POST /api/crm/leads/{id}/qualify
     */
    public function qualify(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'deal_title'          => ['nullable', 'string', 'max:255'],
            'deal_amount'         => ['nullable', 'numeric', 'min:0'],
            'deal_status_id'      => ['nullable', 'integer'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $dealTitle = !empty($validated['deal_title']) ? $validated['deal_title'] : ($lead->company_name ?: ($lead->contact_person ?: "Lead #{$lead->id} Deal"));
        $dealAmount = isset($validated['deal_amount']) ? (float)$validated['deal_amount'] : (float)($lead->expected_amount ?? 0);

        $result = DB::transaction(function () use ($lead, $validated, $dealTitle, $dealAmount, $tenantId, $companyId, $branchId) {
            // 1. Create or Find Customer
            $customer = Customer::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'email'     => $lead->email ?: ($lead->company_email ?: "lead_{$lead->id}@placeholder.com")
                ],
                [
                    'company_id'   => $companyId,
                    'branch_id'    => $branchId,
                    'name'         => $lead->name ?: ($lead->company_name ?: 'Valued Client'),
                    'company_name' => $lead->company_name,
                    'phone'        => $lead->phone ?: $lead->company_phone,
                    'status'       => 'active',
                ]
            );

            // 2. Create or Find CrmAccount
            $account = CrmAccount::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name'      => $lead->company_name ?: ($lead->name ?: 'Valued Account')
                ],
                [
                    'company_id'  => $companyId,
                    'branch_id'   => $branchId,
                    'customer_id' => $customer->id,
                    'email'       => $lead->email ?: $lead->company_email,
                    'phone'       => $lead->phone ?: $lead->company_phone,
                    'status'      => 'active',
                    'owner_id'    => $lead->lead_owner_id ?: (auth()->id() ?? 1),
                ]
            );

            // 3. Create or Find CrmContact
            $contact = CrmContact::firstOrCreate(
                [
                    'tenant_id'      => $tenantId,
                    'crm_account_id' => $account->id,
                    'name'           => $lead->contact_person ?: ($lead->name ?: 'Primary Contact')
                ],
                [
                    'email'      => $lead->email,
                    'phone'      => $lead->phone,
                    'is_primary' => true,
                ]
            );

            // 4. Create Deal
            $dealNo = 'DEAL-' . strtoupper(bin2hex(random_bytes(4)));
            $stageName = 'Qualification';
            if (!empty($validated['deal_status_id'])) {
                $statusObj = DealStatus::find($validated['deal_status_id']);
                if ($statusObj) {
                    $stageName = $statusObj->name;
                }
            }

            $deal = CrmDeal::create([
                'tenant_id'        => $tenantId,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'crm_account_id'   => $account->id,
                'crm_contact_id'   => $contact->id,
                'deal_number'      => $dealNo,
                'title'            => $dealTitle,
                'stage'            => $stageName,
                'estimated_value'  => $dealAmount,
                'closing_date'     => $validated['expected_close_date'] ?? now()->addDays(30)->toDateString(),
                'owner_id'         => $lead->lead_owner_id ?: (auth()->id() ?? 1),
                'notes'            => $validated['notes'] ?? null,
            ]);

            // 5. Update Lead
            $lead->crm_account_id = $account->id;
            $lead->crm_contact_id = $contact->id;
            $lead->crm_deal_id    = $deal->id;
            $lead->status         = 'Qualified';
            $lead->save();

            return [
                'lead'     => $lead->fresh(),
                'account'  => $account,
                'customer' => $customer,
                'contact'  => $contact,
                'deal'     => $deal,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Lead qualified and converted to Deal, Account, & Customer successfully',
            'data'    => $result,
        ], 201);
    }

    /**
     * DELETE /api/crm/leads/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully',
        ]);
    }

    /**
     * PATCH /api/crm/leads/{id}/owner
     */
    public function updateOwner(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => "Lead with ID {$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'lead_owner_id' => 'nullable|integer|exists:users,id',
            'owner_id'      => 'nullable|integer|exists:users,id',
            'note'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ownerId = $request->input('lead_owner_id', $request->input('owner_id'));
        $lead->lead_owner_id = $ownerId;
        $lead->save();

        if ($lead->crm_deal_id) {
            \App\Domains\CRM\Models\CrmDeal::where('id', $lead->crm_deal_id)->update(['owner_id' => $lead->lead_owner_id]);
        }

        if ($request->filled('note')) {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead,
                'note',
                null,
                $request->input('note'),
                "Assignment Note: {$request->input('note')}"
            );
        }

        $targetUser = $ownerId ? User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';

        return response()->json([
            'success'       => true,
            'message'       => "Lead owner reassigned successfully to {$ownerName}.",
            'lead_id'       => $lead->id,
            'owner_id'      => $ownerId,
            'owner_name'    => $ownerName,
            'data'          => $lead->load('owner'),
        ]);
    }

    /**
     * POST /api/crm/leads/bulk-assign
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'lead_ids'      => 'required|array|min:1',
            'lead_ids.*'    => 'required|integer|exists:leads,id',
            'lead_owner_id' => 'nullable|integer|exists:users,id',
            'owner_id'      => 'nullable|integer|exists:users,id',
            'note'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ownerId = $request->input('lead_owner_id', $request->input('owner_id'));
        $targetUser = $ownerId ? User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';
        $note = $request->input('note');

        $leads = Lead::where('tenant_id', $tenantId)
            ->whereIn('id', $request->input('lead_ids'))
            ->get();

        $updatedCount = 0;
        foreach ($leads as $lead) {
            $lead->lead_owner_id = $ownerId;
            $lead->save();

            if ($lead->crm_deal_id) {
                \App\Domains\CRM\Models\CrmDeal::where('id', $lead->crm_deal_id)->update(['owner_id' => $ownerId]);
            }

            if (!empty($note)) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $lead,
                    'note',
                    null,
                    $note,
                    "Bulk Assignment Note: {$note}"
                );
            }
            $updatedCount++;
        }

        return response()->json([
            'success'       => true,
            'message'       => "Successfully assigned {$updatedCount} lead(s) to {$ownerName}.",
            'updated_count' => $updatedCount,
            'owner_id'      => $ownerId,
            'owner_name'    => $ownerName,
        ]);
    }

    /**
     * PATCH /api/crm/leads/{id}/requirement
     */
    public function updateRequirement(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => "Lead with ID {$id} not found."], 404);
        }

        $lead->requirement = $request->input('requirement');
        $lead->save();

        return response()->json([
            'success' => true,
            'message' => 'Lead requirement updated.',
            'data'    => $lead,
        ]);
    }

    /**
     * GET /api/crm/leads/kanban
     */
    public function kanban(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $statuses = LeadStatus::where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $allLeads = Lead::where('tenant_id', $tenantId)->with('owner')->get();

        $columns = [];
        foreach ($statuses as $st) {
            $leadsInStatus = $allLeads->where('status', $st->name)->values();
            $columns[$st->name] = [
                'status' => $st,
                'count'  => $leadsInStatus->count(),
                'leads'  => $leadsInStatus,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $columns,
        ]);
    }

    /**
     * POST /api/crm/leads/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $lead = Lead::where('tenant_id', $tenantId)->onlyTrashed()->find($id);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => "Deleted Lead with ID {$id} not found."], 404);
        }

        $lead->restore();

        return response()->json([
            'success' => true,
            'message' => 'Lead restored successfully',
            'data'    => $lead,
        ]);
    }
}

