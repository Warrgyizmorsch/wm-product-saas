<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CrmAccountApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = company_id() ?? $user?->company_id;
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/crm/accounts/meta
     */
    public function meta(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $owners = User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'data'    => [
                'account_types' => ['Customer', 'Partner', 'Vendor', 'Distributor', 'Other'],
                'statuses'      => ['active', 'inactive', 'archived'],
                'owners'        => $owners,
            ],
        ]);
    }

    /**
     * GET /api/crm/accounts
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = CrmAccount::where('tenant_id', $tenantId)
            ->with(['contacts', 'deals:id,crm_account_id,title,stage,estimated_value', 'customer:id,name,email']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $accounts = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $accounts->items(),
            'meta'    => [
                'current_page' => $accounts->currentPage(),
                'last_page'    => $accounts->lastPage(),
                'per_page'     => $accounts->perPage(),
                'total'        => $accounts->total(),
            ],
        ]);
    }

    /**
     * GET /api/crm/accounts/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $account = CrmAccount::where('tenant_id', $tenantId)
            ->with(['contacts', 'deals', 'customer'])
            ->find($id);

        if (!$account) {
            return response()->json(['success' => false, 'message' => "Account #{$id} not found."], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $account,
        ]);
    }

    /**
     * POST /api/crm/accounts
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|max:255',
            'phone'           => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'website'         => 'nullable|string|max:255',
            'industry'        => 'nullable|string|max:255',
            'industry_type'   => 'nullable|string|max:255',
            'gstin'           => 'nullable|string|max:50',
            'credit_limit'    => 'nullable|numeric|min:0',
            'opening_balance' => 'nullable|numeric|min:0',
            'account_type'    => 'nullable|string|max:50',
            'street'          => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:100',
            'country'         => 'nullable|string|max:100',
            'zip_code'        => 'nullable|string|regex:/^[0-9]+$/|max:10',
            'billing_street'  => 'nullable|string|max:255',
            'billing_city'    => 'nullable|string|max:100',
            'billing_state'   => 'nullable|string|max:100',
            'billing_country' => 'nullable|string|max:100',
            'billing_zip'     => 'nullable|string|regex:/^[0-9]+$/|max:10',
            'owner_id'        => 'nullable|integer|exists:users,id',
            'status'          => 'nullable|string|in:active,inactive',
            // Primary contact fields (flat from screen)
            'contact_name'    => 'nullable|string|max:255',
            'designation'     => 'nullable|string|max:255',
            'role'            => 'nullable|string|max:100',
            'contact_email'   => 'nullable|email|max:255',
            'contact_phone'   => 'nullable|string|regex:/^[0-9]+$/|max:20',
            // Nested contacts array
            'contacts'        => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email'=> 'nullable|email|max:255',
            'contacts.*.phone'=> 'nullable|string|regex:/^[0-9]+$/|max:20',
            'contacts.*.designation' => 'nullable|string|max:255',
            'contacts.*.role' => 'nullable|string|max:100',
        ], $this->accountValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $ownerId = $validated['owner_id'] ?? (auth()->id() ?? User::where('tenant_id', $tenantId)->value('id') ?? 1);

        $industry = $validated['industry_type'] ?? ($validated['industry'] ?? null);
        $street = $validated['street'] ?? ($validated['billing_street'] ?? null);
        $city = $validated['city'] ?? ($validated['billing_city'] ?? null);
        $state = $validated['state'] ?? ($validated['billing_state'] ?? null);
        $country = $validated['country'] ?? ($validated['billing_country'] ?? null);
        $zipCode = $validated['zip_code'] ?? ($validated['billing_zip'] ?? null);

        $account = CrmAccount::create([
            'tenant_id'       => $tenantId,
            'company_id'      => $companyId,
            'branch_id'       => $branchId,
            'account_number'  => 'ACC-' . strtoupper(bin2hex(random_bytes(3))),
            'name'            => $validated['name'],
            'email'           => $validated['email'] ?? null,
            'phone'           => $validated['phone'] ?? null,
            'website'         => $validated['website'] ?? null,
            'industry_type'   => $industry,
            'gstin'           => $validated['gstin'] ?? null,
            'credit_limit'    => $validated['credit_limit'] ?? 0.00,
            'street'          => $street,
            'city'            => $city,
            'state'           => $state,
            'country'         => $country,
            'zip_code'        => $zipCode,
            'owner_id'        => $ownerId,
            'status'          => $validated['status'] ?? 'active',
        ]);

        if (!empty($validated['contact_name']) || !empty($validated['contact_email']) || !empty($validated['contact_phone'])) {
            CrmContact::create([
                'tenant_id'      => $tenantId,
                'company_id'     => $companyId,
                'branch_id'      => $branchId,
                'crm_account_id' => $account->id,
                'name'           => $validated['contact_name'] ?? $validated['name'],
                'designation'    => $validated['designation'] ?? null,
                'role'           => $validated['role'] ?? 'Purchase Decision Maker',
                'email'          => $validated['contact_email'] ?? $validated['email'] ?? null,
                'phone'          => $validated['contact_phone'] ?? $validated['phone'] ?? null,
                'mobile'         => $validated['contact_phone'] ?? null,
                'is_primary'     => true,
                'status'         => 'active',
            ]);
        }

        if (!empty($validated['contacts'])) {
            foreach ($validated['contacts'] as $contactData) {
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'company_id'     => $companyId,
                    'branch_id'      => $branchId,
                    'crm_account_id' => $account->id,
                    'name'           => $contactData['name'],
                    'email'          => $contactData['email'] ?? null,
                    'phone'          => $contactData['phone'] ?? null,
                    'mobile'         => $contactData['phone'] ?? null,
                    'designation'    => $contactData['designation'] ?? null,
                    'role'           => $contactData['role'] ?? 'Purchase Decision Maker',
                    'is_primary'     => false,
                    'status'         => 'active',
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'CRM Account created successfully.',
            'data'    => $account->load('contacts'),
        ], 201);
    }

    /**
     * POST /api/crm/accounts/{id}/contacts
     */
    public function storeContact(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $account = CrmAccount::where('tenant_id', $tenantId)->find($id);

        if (!$account) {
            return response()->json(['success' => false, 'message' => "Account #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'mobile'      => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'designation' => 'nullable|string|max:100',
            'role'        => 'nullable|string|max:100',
            'is_primary'  => 'nullable|boolean',
        ], $this->accountValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $contact = CrmContact::create(array_merge($validator->validated(), [
            'tenant_id'      => $tenantId,
            'crm_account_id' => $account->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Contact added to account successfully.',
            'data'    => $contact,
        ], 201);
    }

    /**
     * PUT/PATCH /api/crm/accounts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $account = CrmAccount::where('tenant_id', $tenantId)->find($id);

        if (!$account) {
            return response()->json(['success' => false, 'message' => "Account #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'sometimes|required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'website'         => 'nullable|string|max:255',
            'industry'        => 'nullable|string|max:255',
            'industry_type'   => 'nullable|string|max:255',
            'gstin'           => 'nullable|string|max:50',
            'credit_limit'    => 'nullable|numeric|min:0',
            'account_type'    => 'nullable|string|max:50',
            'street'          => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:100',
            'country'         => 'nullable|string|max:100',
            'zip_code'        => 'nullable|string|regex:/^[0-9]+$/|max:10',
            'billing_street'  => 'nullable|string|max:255',
            'billing_city'    => 'nullable|string|max:100',
            'billing_state'   => 'nullable|string|max:100',
            'billing_country' => 'nullable|string|max:100',
            'billing_zip'     => 'nullable|string|regex:/^[0-9]+$/|max:10',
            'owner_id'        => 'nullable|integer|exists:users,id',
            'status'          => 'nullable|string|in:active,inactive',
        ], $this->accountValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (isset($validated['industry']) && !isset($validated['industry_type'])) {
            $validated['industry_type'] = $validated['industry'];
            unset($validated['industry']);
        }
        if (isset($validated['billing_street']) && !isset($validated['street'])) {
            $validated['street'] = $validated['billing_street'];
            unset($validated['billing_street']);
        }
        if (isset($validated['billing_city']) && !isset($validated['city'])) {
            $validated['city'] = $validated['billing_city'];
            unset($validated['billing_city']);
        }
        if (isset($validated['billing_state']) && !isset($validated['state'])) {
            $validated['state'] = $validated['billing_state'];
            unset($validated['billing_state']);
        }
        if (isset($validated['billing_country']) && !isset($validated['country'])) {
            $validated['country'] = $validated['billing_country'];
            unset($validated['billing_country']);
        }
        if (isset($validated['billing_zip']) && !isset($validated['zip_code'])) {
            $validated['zip_code'] = $validated['billing_zip'];
            unset($validated['billing_zip']);
        }

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'CRM Account updated successfully.',
            'data'    => $account->fresh(['contacts', 'deals']),
        ]);
    }

    /**
     * PATCH /api/crm/accounts/{id}/owner
     */
    public function updateOwner(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $account = CrmAccount::where('tenant_id', $tenantId)->find($id);

        if (!$account) {
            return response()->json(['success' => false, 'message' => "Account #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_owner_id' => 'nullable|integer|exists:users,id',
            'owner_id'         => 'nullable|integer|exists:users,id',
            'note'             => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ownerId = $request->input('account_owner_id', $request->input('owner_id'));
        $account->update(['owner_id' => $ownerId]);

        $targetUser = $ownerId ? User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';

        return response()->json([
            'success'       => true,
            'message'       => "Account owner successfully updated to {$ownerName}.",
            'account_id'    => $account->id,
            'owner_id'      => $ownerId,
            'owner_name'    => $ownerName,
            'data'          => $account->fresh(['contacts', 'deals']),
        ]);
    }

    /**
     * POST /api/crm/accounts/bulk-assign
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'account_ids'      => 'required|array|min:1',
            'account_ids.*'    => 'required|integer|exists:crm_accounts,id',
            'account_owner_id' => 'nullable|integer|exists:users,id',
            'owner_id'         => 'nullable|integer|exists:users,id',
            'note'             => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ownerId = $request->input('account_owner_id', $request->input('owner_id'));
        $targetUser = $ownerId ? User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';

        $accounts = CrmAccount::where('tenant_id', $tenantId)
            ->whereIn('id', $request->input('account_ids'))
            ->get();

        $updatedCount = 0;
        foreach ($accounts as $account) {
            $account->update(['owner_id' => $ownerId]);
            $updatedCount++;
        }

        return response()->json([
            'success'       => true,
            'message'       => "Successfully assigned {$updatedCount} account(s) to {$ownerName}.",
            'updated_count' => $updatedCount,
            'owner_id'      => $ownerId,
            'owner_name'    => $ownerName,
        ]);
    }

    /**
     * DELETE /api/crm/accounts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $account = CrmAccount::where('tenant_id', $tenantId)->find($id);

        if (!$account) {
            return response()->json(['success' => false, 'message' => "Account #{$id} not found."], 404);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => "Account #{$id} deleted successfully.",
        ]);
    }

    private function accountValidationMessages(): array
    {
        return [
            'name.required'          => 'Company Name is required.',
            'email.required'         => 'Contact Email is required.',
            'email.email'            => 'Please enter a valid email address for Company Email.',
            'phone.regex'            => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'contact_phone.regex'    => 'Primary contact phone number must contain digits/numbers only (no special characters or letters).',
            'contacts.*.phone.regex' => 'Contact phone number must contain digits/numbers only (no special characters or letters).',
            'contact_email.email'    => 'Please enter a valid email address for Contact.',
            'zip_code.regex'         => 'Zip / Postal code must contain digits/numbers only (no special characters or letters).',
            'billing_zip.regex'      => 'Zip / Postal code must contain digits/numbers only (no special characters or letters).',
            'credit_limit.numeric'   => 'Credit limit must be a valid number.',
            'opening_balance.numeric'=> 'Opening balance must be a valid number.',
            'mobile.regex'           => 'Mobile number must contain digits/numbers only (no special characters or letters).',
        ];
    }
}
