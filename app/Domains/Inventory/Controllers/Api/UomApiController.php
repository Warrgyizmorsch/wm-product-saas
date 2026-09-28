<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UomApiController extends Controller
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
     * GET /api/inventory/uoms
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Uom::query()->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $uoms = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $uoms,
        ]);
    }

    /**
     * POST /api/inventory/uoms
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('uoms', 'code')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $uom = Uom::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'name'        => $validated['name'],
            'code'        => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'UOM created successfully',
            'data'    => $uom,
        ], 201);
    }

    /**
     * POST /api/inventory/uoms/quick-create
     */
    public function quickCreate(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('uoms', 'code')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $uom = Uom::create([
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'name'       => $validated['name'],
            'code'       => strtoupper($validated['code']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'UOM quick-created successfully',
            'data'    => [
                'id'   => $uom->id,
                'name' => $uom->name,
                'code' => $uom->code,
            ],
        ], 201);
    }
}
