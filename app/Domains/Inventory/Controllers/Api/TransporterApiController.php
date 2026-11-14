<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Platform\Models\Transporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransporterApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = company_id() ?? $user?->company_id;
        $branchId  = branch_id() ?? $user?->branch_id;

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/inventory/transporters
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Transporter::query()
            ->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('transporter_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $transporters = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $transporters->items(),
            'meta'    => [
                'current_page' => $transporters->currentPage(),
                'last_page'    => $transporters->lastPage(),
                'per_page'     => $transporters->perPage(),
                'total'        => $transporters->total(),
            ],
        ]);
    }

    /**
     * POST /api/inventory/transporters
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'name'                  => ['required', 'string', 'max:255'],
            'code'                  => ['nullable', 'string', 'max:50'],
            'transporter_id'        => ['nullable', 'string', 'max:50'],
            'gstin'                 => ['nullable', 'string', 'max:20'],
            'pan_number'            => ['nullable', 'string', 'max:20'],
            'phone'                 => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'email'                 => ['nullable', 'email', 'max:150'],
            'address'               => ['nullable', 'string', 'max:500'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'state'                 => ['nullable', 'string', 'max:100'],
            'pincode'               => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:10'],
            'transport_mode'        => ['nullable', 'string', 'max:50'],
            'fleet_type'            => ['nullable', 'string', 'max:100'],
            'serviceable_zones'     => ['nullable', 'string'],
            'status'                => ['nullable', 'string', 'in:active,inactive'],
            'tds_section'           => ['nullable', 'string', 'max:50'],
            'tds_rate'              => ['nullable', 'numeric'],
            'has_194c_declaration'  => ['nullable', 'boolean'],
            'declaration_reference' => ['nullable', 'string', 'max:100'],
            'sac_code'              => ['nullable', 'string', 'max:20'],
            'bank_name'             => ['nullable', 'string', 'max:150'],
            'branch_name'           => ['nullable', 'string', 'max:150'],
            'account_name'          => ['nullable', 'string', 'max:150'],
            'account_number'        => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code'             => ['nullable', 'string', 'max:20'],
            'payment_terms'         => ['nullable', 'string', 'max:100'],
            'opening_balance'       => ['nullable', 'numeric'],
            'contact_person_name'   => ['nullable', 'string', 'max:150'],
            'contact_person_phone'  => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'contact_person_email'  => ['nullable', 'email', 'max:150'],
        ], [
            'email.email'                => 'The email field must be a valid email address.',
            'phone.regex'                => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'                  => 'Phone number cannot exceed 20 digits.',
            'contact_person_phone.regex' => 'Contact person phone must contain digits/numbers only (no special characters or letters).',
            'contact_person_phone.max'   => 'Contact person phone cannot exceed 20 digits.',
            'contact_person_email.email' => 'Contact person email must be a valid email address.',
            'pincode.regex'              => 'Pincode must contain digits/numbers only.',
            'pincode.max'                => 'Pincode cannot exceed 10 digits.',
            'account_number.regex'       => 'Bank account number must contain digits/numbers only.',
            'account_number.max'         => 'Bank account number cannot exceed 50 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $transporter = Transporter::create(array_merge($validated, [
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'status'     => $validated['status'] ?? 'active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Transporter created successfully',
            'data'    => $transporter,
        ], 201);
    }

    /**
     * GET /api/inventory/transporters/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $transporter = Transporter::where('tenant_id', $tenantId)->find($id);

        if (!$transporter) {
            return response()->json([
                'success' => false,
                'message' => 'Transporter not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $transporter,
        ]);
    }

    /**
     * PUT/PATCH /api/inventory/transporters/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $transporter = Transporter::where('tenant_id', $tenantId)->find($id);

        if (!$transporter) {
            return response()->json([
                'success' => false,
                'message' => 'Transporter not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                  => ['sometimes', 'required', 'string', 'max:255'],
            'code'                  => ['nullable', 'string', 'max:50'],
            'transporter_id'        => ['nullable', 'string', 'max:50'],
            'gstin'                 => ['nullable', 'string', 'max:20'],
            'pan_number'            => ['nullable', 'string', 'max:20'],
            'phone'                 => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'email'                 => ['nullable', 'email', 'max:150'],
            'address'               => ['nullable', 'string', 'max:500'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'state'                 => ['nullable', 'string', 'max:100'],
            'pincode'               => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:10'],
            'transport_mode'        => ['nullable', 'string', 'max:50'],
            'fleet_type'            => ['nullable', 'string', 'max:100'],
            'serviceable_zones'     => ['nullable', 'string'],
            'status'                => ['nullable', 'string', 'in:active,inactive'],
            'tds_section'           => ['nullable', 'string', 'max:50'],
            'tds_rate'              => ['nullable', 'numeric'],
            'has_194c_declaration'  => ['nullable', 'boolean'],
            'declaration_reference' => ['nullable', 'string', 'max:100'],
            'sac_code'              => ['nullable', 'string', 'max:20'],
            'bank_name'             => ['nullable', 'string', 'max:150'],
            'branch_name'           => ['nullable', 'string', 'max:150'],
            'account_name'          => ['nullable', 'string', 'max:150'],
            'account_number'        => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code'             => ['nullable', 'string', 'max:20'],
            'payment_terms'         => ['nullable', 'string', 'max:100'],
            'opening_balance'       => ['nullable', 'numeric'],
            'contact_person_name'   => ['nullable', 'string', 'max:150'],
            'contact_person_phone'  => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'contact_person_email'  => ['nullable', 'email', 'max:150'],
        ], [
            'email.email'                => 'The email field must be a valid email address.',
            'phone.regex'                => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'                  => 'Phone number cannot exceed 20 digits.',
            'contact_person_phone.regex' => 'Contact person phone must contain digits/numbers only (no special characters or letters).',
            'contact_person_phone.max'   => 'Contact person phone cannot exceed 20 digits.',
            'contact_person_email.email' => 'Contact person email must be a valid email address.',
            'pincode.regex'              => 'Pincode must contain digits/numbers only.',
            'pincode.max'                => 'Pincode cannot exceed 10 digits.',
            'account_number.regex'       => 'Bank account number must contain digits/numbers only.',
            'account_number.max'         => 'Bank account number cannot exceed 50 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $transporter->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transporter updated successfully',
            'data'    => $transporter->fresh(),
        ]);
    }

    /**
     * DELETE /api/inventory/transporters/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $transporter = Transporter::where('tenant_id', $tenantId)->find($id);

        if (!$transporter) {
            return response()->json([
                'success' => false,
                'message' => 'Transporter not found',
            ], 404);
        }

        $transporter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transporter deleted successfully',
        ]);
    }

    /**
     * POST /api/inventory/transporters/quick-create
     */
    public function quickCreate(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'name'           => ['required', 'string', 'max:255'],
            'code'           => ['nullable', 'string', 'max:50'],
            'transporter_id' => ['nullable', 'string', 'max:50'],
            'transport_mode' => ['nullable', 'string', 'max:50'],
            'phone'          => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'email'          => ['nullable', 'email', 'max:150'],
            'gstin'          => ['nullable', 'string', 'max:20'],
            'pan_number'     => ['nullable', 'string', 'max:20'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
        ], [
            'email.email' => 'The email field must be a valid email address.',
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'   => 'Phone number cannot exceed 20 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (empty($validated['code'])) {
            $count = Transporter::where('tenant_id', $tenantId)->count() + 1;
            $validated['code'] = 'TRP-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        }

        $transporter = Transporter::create(array_merge($validated, [
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'status'     => 'active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Transporter quick-created successfully',
            'data'    => $transporter,
        ], 201);
    }
}
