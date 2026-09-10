<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Core\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * LeadApiController
 * 
 * Location: app/Domains/CRM/Controllers/Api/LeadApiController.php (CRM Module)
 * 
 * Unified API endpoint for creating Single Lead or Bulk Leads.
 * Endpoint: POST /api/crm/leads
 * Authentication: Laravel Sanctum (Bearer Token)
 */
class LeadApiController extends Controller
{
    /**
     * Shared validation rules with dynamic conditional checks:
     * 1) If company_name is present => company_email is REQUIRED; contact_person & email are OPTIONAL.
     * 2) If company_name is NOT present => company_email is OPTIONAL; contact_person & email are REQUIRED.
     */
    private function leadRules(array $data = []): array
    {
        $hasCompany = !empty(trim($data['company_name'] ?? ''));

        return [
            'company_name'       => 'nullable|string|max:255',
            'gstin'              => 'nullable|string|max:100',
            'company_email'      => $hasCompany ? 'required|email|max:255' : 'nullable|email|max:255',
            'company_phone'      => 'nullable|string|max:50',
            'contact_person'     => $hasCompany ? 'nullable|string|max:255' : 'required|string|max:255',
            'designation'        => 'nullable|string|max:255',
            'email'              => $hasCompany ? 'nullable|email|max:255' : 'required|email|max:255',
            'phone'              => 'nullable|string|max:50',
            'lead_owner_id'      => 'nullable|integer',
            'expected_sale_date' => 'nullable|date',
            'requirement'        => 'nullable|string',
            'industry_type'      => 'nullable|string|max:255',
            'source'             => 'nullable|string|max:255',
            'priority'           => 'nullable|string|in:Low,Medium,High',
            'segment'            => 'nullable|string|max:255',
            'country'            => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:255',
            'address'            => 'nullable|string',
            
            // Nested arrays matching web form repeators
            'additional_contacts'               => 'nullable|array',
            'additional_contacts.*.name'        => 'nullable|string|max:255',
            'additional_contacts.*.designation' => 'nullable|string|max:255',
            'additional_contacts.*.phone'       => 'nullable|string|max:50',
            'additional_contacts.*.email'       => 'nullable|email|max:255',
        ];
    }

    /**
     * Custom validation messages for conditional fields.
     */
    private function leadValidationMessages(): array
    {
        return [
            'company_email.required' => 'company_email is required when company_name is provided.',
            'contact_person.required' => 'contact_person is required when company_name is not provided.',
            'email.required'          => 'email (contact email) is required when company_name is not provided.',
        ];
    }

    /**
     * Build Lead model instance with tenant and default values.
     */
    private function buildLead(array $data, int $tenantId, int $companyId, ?int $branchId): Lead
    {
        // 1) Purely set lead_type in code: If company_name exists => b2b, otherwise => b2c
        $leadType = !empty(trim($data['company_name'] ?? '')) ? 'b2b' : 'b2c';

        // 2) Automatically default call_date to current timestamp (now)
        $callDate = now();

        // Parse expected sale date safely
        $expectedSaleDate = null;
        if (!empty($data['expected_sale_date'])) {
            try {
                $expectedSaleDate = Carbon::parse($data['expected_sale_date']);
            } catch (\Exception $e) {
                $expectedSaleDate = null;
            }
        }

        // Clean additional_contacts array if passed
        $additionalContacts = null;
        if (!empty($data['additional_contacts']) && is_array($data['additional_contacts'])) {
            $additionalContacts = array_values(array_filter($data['additional_contacts'], function ($contact) {
                return !empty($contact['name']) || !empty($contact['designation']) || !empty($contact['phone']) || !empty($contact['email']);
            }));
        }

        return new Lead([
            'tenant_id'           => $tenantId,
            'company_id'          => $companyId,
            'branch_id'           => $branchId,
            'lead_type'           => $leadType,
            'call_date'           => $callDate,
            'company_name'        => $data['company_name']    ?? null,
            'gstin'               => $data['gstin']           ?? null,
            'company_email'       => $data['company_email']   ?? null,
            'company_phone'       => $data['company_phone']   ?? null,
            'contact_person'      => $data['contact_person']  ?? null,
            'designation'         => $data['designation']     ?? null,
            'email'               => $data['email']           ?? null,
            'phone'               => $data['phone']           ?? null,
            'lead_owner_id'       => $data['lead_owner_id']   ?? (auth()->id() ?? 1),
            'expected_amount'     => null,
            'expected_sale_date'  => $expectedSaleDate,
            'requirement'         => $data['requirement']     ?? null,
            'industry_type'       => $data['industry_type']   ?? null,
            'source'              => $data['source']          ?? null,
            'priority'            => $data['priority']        ?? null,
            'segment'             => $data['segment']         ?? null,
            'country'             => $data['country']         ?? null,
            'state'               => $data['state']           ?? null,
            'city'                => $data['city']            ?? null,
            'address'             => $data['address']         ?? null,
            'status'              => 'New',
            'additional_contacts' => $additionalContacts,
            'items'               => null,
        ]);
    }

    /**
     * Resolve Tenant & Company Context
     */
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [$tenantId, $companyId, $branchId];
    }

    /**
     * POST /api/crm/leads
     * Single Endpoint for both Single Lead and Bulk Leads Import
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();

        // 1) Direct JSON array: [ {...}, {...} ]
        if (is_array($payload) && array_is_list($payload)) {
            return $this->processBulkLeads($payload);
        }

        // 2) Object with "leads" array: { "leads": [ {...}, {...} ] }
        if (isset($payload['leads']) && is_array($payload['leads'])) {
            return $this->processBulkLeads($payload['leads']);
        }

        // 3) Single Lead Object: { "company_name": "ABC", ... }
        $validator = Validator::make($payload, $this->leadRules($payload), $this->leadValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
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
                    'company'     => $lead->company_name,
                    'status'      => $lead->status,
                    'created_at'  => $lead->created_at,
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
     * Process bulk list of leads
     */
    private function processBulkLeads(array $leadsList): JsonResponse
    {
        if (count($leadsList) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum 500 leads allowed per request.',
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
