<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * LeadApiController
 * 
 * Location: app/Domains/CRM/Controllers/Api/LeadApiController.php (CRM Module)
 * 
 * Enterprise-Grade Lead Export & Import REST API Kit.
 * Endpoints:
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
     * GET /api/crm/leads/export (and GET /api/crm/leads)
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

        // 13. Pagination / Limit Handling
        $totalCount = (clone $query)->count();
        $limit = $request->filled('limit') ? min(max((int)$request->input('limit'), 1), 1000) : null;
        
        if ($request->filled('page') && $limit) {
            $page = max((int)$request->input('page'), 1);
            $query->forPage($page, $limit);
        } elseif ($limit !== null) {
            $query->take($limit);
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

                // Complete Flat Raw Database Fields for 1-to-1 roundtrip synchronization
                'raw' => [
                    'id'                  => $lead->id,
                    'tenant_id'           => $lead->tenant_id,
                    'company_id'          => $lead->company_id,
                    'branch_id'           => $lead->branch_id,
                    'lead_number'         => $lead->lead_number,
                    'lead_owner_id'       => $lead->lead_owner_id,
                    'call_date'           => $lead->call_date ? $lead->call_date->format('Y-m-d H:i:s') : null,
                    'company_name'        => $lead->company_name,
                    'company_email'       => $lead->company_email,
                    'company_phone'       => $lead->company_phone,
                    'gstin'               => $lead->gstin,
                    'lead_type'           => $lead->lead_type,
                    'contact_person'      => $lead->contact_person,
                    'designation'         => $lead->designation,
                    'email'               => $lead->email,
                    'phone'               => $lead->phone,
                    'requirement'         => $lead->requirement,
                    'crm_account_id'      => $lead->crm_account_id,
                    'crm_contact_id'      => $lead->crm_contact_id,
                    'crm_deal_id'         => $lead->crm_deal_id,
                    'converted_at'        => $lead->converted_at,
                    'expected_amount'     => $lead->expected_amount,
                    'expected_sale_date'  => $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : null,
                    'source'              => $lead->source,
                    'utm_source'          => $lead->utm_source,
                    'utm_medium'          => $lead->utm_medium,
                    'utm_campaign'        => $lead->utm_campaign,
                    'utm_term'            => $lead->utm_term,
                    'utm_content'         => $lead->utm_content,
                    'priority'            => $lead->priority,
                    'segment'             => $lead->segment,
                    'industry_type'       => $lead->industry_type,
                    'country'             => $lead->country,
                    'state'               => $lead->state,
                    'city'                => $lead->city,
                    'address'             => $lead->address,
                    'product_ids'         => $lead->product_ids,
                    'product_items'       => $lead->product_items,
                    'status'              => $lead->status,
                    'next_followup_date'  => $lead->next_followup_date ? $lead->next_followup_date->format('Y-m-d H:i:s') : null,
                    'is_customer'         => (bool)$lead->is_customer,
                    'created_at'          => $lead->created_at ? $lead->created_at->toIso8601String() : null,
                    'updated_at'          => $lead->updated_at ? $lead->updated_at->toIso8601String() : null,
                ],
            ];
        });

        return response()->json([
            'success'          => true,
            'api_version'      => 'v1',
            'exported_at'      => now()->toIso8601String(),
            'total_records'    => $totalCount,
            'count'            => $exportedLeads->count(),
            'filters_applied'  => array_filter($request->only([
                'status', 'lead_type', 'priority', 'source', 'segment', 'industry_type',
                'lead_owner_id', 'country', 'state', 'city', 'is_customer', 'from_date', 'to_date', 'search', 'date_filter_by'
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
        if (!$leadOwnerId && !empty($data['owner_email'])) {
            $owner = User::where('tenant_id', $tenantId)->where('email', $data['owner_email'])->first();
            if ($owner) {
                $leadOwnerId = $owner->id;
            }
        }
        if (!$leadOwnerId) {
            $leadOwnerId = auth()->id() ?? 1;
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
}
