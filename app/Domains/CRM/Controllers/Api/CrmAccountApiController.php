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
        $companyId = $user?->company_id ?? (company_id() ?? 1);
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
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'website'        => 'nullable|string|max:255',
            'industry'       => 'nullable|string|max:255',
            'gstin'          => 'nullable|string|max:50',
            'account_type'   => 'nullable|string|max:50',
            'billing_street' => 'nullable|string',
            'billing_city'   => 'nullable|string|max:100',
            'billing_state'  => 'nullable|string|max:100',
            'billing_country'=> 'nullable|string|max:100',
            'owner_id'       => 'nullable|integer',
            'status'         => 'nullable|string|in:active,inactive',
            'contacts'       => 'nullable|array',
            'contacts.*.name'=> 'required|string|max:255',
            'contacts.*.email'=> 'nullable|email|max:255',
            'contacts.*.phone'=> 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $ownerId = $validated['owner_id'] ?? (auth()->id() ?? User::where('tenant_id', $tenantId)->value('id') ?? 1);

        $account = CrmAccount::create([
            'tenant_id'       => $tenantId,
            'company_id'      => $companyId,
            'branch_id'       => $branchId,
            'account_number'  => 'ACC-' . strtoupper(bin2hex(random_bytes(3))),
            'name'            => $validated['name'],
            'email'           => $validated['email'] ?? null,
            'phone'           => $validated['phone'] ?? null,
            'website'         => $validated['website'] ?? null,
            'industry'        => $validated['industry'] ?? null,
            'gstin'           => $validated['gstin'] ?? null,
            'account_type'    => $validated['account_type'] ?? 'Customer',
            'billing_street'  => $validated['billing_street'] ?? null,
            'billing_city'    => $validated['billing_city'] ?? null,
            'billing_state'   => $validated['billing_state'] ?? null,
            'billing_country' => $validated['billing_country'] ?? null,
            'owner_id'        => $ownerId,
            'status'          => $validated['status'] ?? 'active',
        ]);

        if (!empty($validated['contacts'])) {
            foreach ($validated['contacts'] as $contactData) {
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'crm_account_id' => $account->id,
                    'name'           => $contactData['name'],
                    'email'          => $contactData['email'] ?? null,
                    'phone'          => $contactData['phone'] ?? null,
                    'is_primary'     => true,
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
            'phone'       => 'nullable|string|max:50',
            'designation' => 'nullable|string|max:100',
            'is_primary'  => 'nullable|boolean',
        ]);

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
            'name'           => 'sometimes|required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'website'        => 'nullable|string|max:255',
            'industry'       => 'nullable|string|max:255',
            'gstin'          => 'nullable|string|max:50',
            'account_type'   => 'nullable|string|max:50',
            'billing_street' => 'nullable|string',
            'billing_city'   => 'nullable|string|max:100',
            'billing_state'  => 'nullable|string|max:100',
            'billing_country'=> 'nullable|string|max:100',
            'owner_id'       => 'nullable|integer',
            'status'         => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $account->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'CRM Account updated successfully.',
            'data'    => $account->fresh(['contacts', 'deals']),
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
}
