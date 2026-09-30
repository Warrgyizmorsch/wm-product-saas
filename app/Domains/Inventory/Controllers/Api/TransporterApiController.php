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
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

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
            'name'                 => ['required', 'string', 'max:255'],
            'code'                 => ['nullable', 'string', 'max:50'],
            'transporter_id'       => ['nullable', 'string', 'max:50'],
            'gstin'                => ['nullable', 'string', 'max:20'],
            'pan_number'           => ['nullable', 'string', 'max:20'],
            'phone'                => ['nullable', 'string', 'max:20'],
            'email'                => ['nullable', 'email', 'max:100'],
            'address'              => ['nullable', 'string', 'max:500'],
            'city'                 => ['nullable', 'string', 'max:100'],
            'state'                => ['nullable', 'string', 'max:100'],
            'pincode'              => ['nullable', 'string', 'max:20'],
            'transport_mode'       => ['nullable', 'string', 'max:50'],
            'fleet_type'           => ['nullable', 'string', 'max:50'],
            'status'               => ['nullable', 'string', 'max:20'],
            'contact_person_name'  => ['nullable', 'string', 'max:100'],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
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
            'name'                 => ['sometimes', 'required', 'string', 'max:255'],
            'code'                 => ['nullable', 'string', 'max:50'],
            'transporter_id'       => ['nullable', 'string', 'max:50'],
            'gstin'                => ['nullable', 'string', 'max:20'],
            'pan_number'           => ['nullable', 'string', 'max:20'],
            'phone'                => ['nullable', 'string', 'max:20'],
            'email'                => ['nullable', 'email', 'max:100'],
            'address'              => ['nullable', 'string', 'max:500'],
            'city'                 => ['nullable', 'string', 'max:100'],
            'state'                => ['nullable', 'string', 'max:100'],
            'pincode'              => ['nullable', 'string', 'max:20'],
            'transport_mode'       => ['nullable', 'string', 'max:50'],
            'fleet_type'           => ['nullable', 'string', 'max:50'],
            'status'               => ['nullable', 'string', 'max:20'],
            'contact_person_name'  => ['nullable', 'string', 'max:100'],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
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
            'transporter_id' => ['nullable', 'string', 'max:50'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'gstin'          => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $transporter = Transporter::create([
            'tenant_id'      => $tenantId,
            'company_id'     => $companyId,
            'branch_id'      => $branchId,
            'name'           => $validated['name'],
            'transporter_id' => $validated['transporter_id'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'gstin'          => $validated['gstin'] ?? null,
            'status'         => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transporter quick-created successfully',
            'data'    => $transporter,
        ], 201);
    }
}
