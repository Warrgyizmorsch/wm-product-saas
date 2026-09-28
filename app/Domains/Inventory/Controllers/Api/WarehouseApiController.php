<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WarehouseApiController extends Controller
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
     * GET /api/inventory/warehouses
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Warehouse::query()->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $warehouses = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $warehouses,
        ]);
    }

    /**
     * POST /api/inventory/warehouses
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'           => ['required', 'string', 'max:255'],
            'code'           => ['required', 'string', 'max:50'],
            'address'        => ['nullable', 'string'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'pincode'        => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', 'in:active,inactive'],
            'is_default'     => ['nullable', 'boolean'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        $warehouse = Warehouse::create([
            'tenant_id'      => $tenantId,
            'company_id'     => $companyId,
            'branch_id'      => $branchId,
            'name'           => $validated['name'],
            'code'           => $validated['code'],
            'address'        => $validated['address'] ?? null,
            'city'           => $validated['city'] ?? null,
            'state'          => $validated['state'] ?? null,
            'pincode'        => $validated['pincode'] ?? null,
            'country'        => $validated['country'] ?? 'India',
            'status'         => $validated['status'] ?? 'active',
            'is_default'     => $validated['is_default'] ?? false,
            'contact_person' => $validated['contact_person'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successfully',
            'data'    => $warehouse,
        ], 201);
    }

    /**
     * GET /api/inventory/warehouses/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($id);

        $stockSummary = ProductWarehouseStock::where('warehouse_id', $id)
            ->with('product:id,name,sku,unit_cost')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => [
                'warehouse'     => $warehouse,
                'stock_summary' => $stockSummary,
            ],
        ]);
    }

    /**
     * PUT/PATCH /api/inventory/warehouses/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $warehouse  = Warehouse::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'code'           => ['sometimes', 'required', 'string', 'max:50'],
            'address'        => ['nullable', 'string'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'pincode'        => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', 'in:active,inactive'],
            'is_default'     => ['nullable', 'boolean'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $warehouse->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Warehouse updated successfully',
            'data'    => $warehouse->fresh(),
        ]);
    }

    /**
     * DELETE /api/inventory/warehouses/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $warehouse  = Warehouse::where('tenant_id', $tenantId)->findOrFail($id);

        $hasStock = ProductWarehouseStock::where('warehouse_id', $id)->where('quantity', '>', 0)->exists();
        if ($hasStock) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with active stock inventory.',
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
        ]);
    }

    /**
     * GET /api/inventory/warehouses/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\WarehouseExport($tenantId, $request->all()),
            'warehouses_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * POST /api/inventory/warehouses/quick-create
     */
    public function quickCreate(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $warehouse = Warehouse::create([
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'name'       => $validated['name'],
            'code'       => strtoupper($validated['code']),
            'address'    => $validated['address'] ?? null,
            'is_default' => Warehouse::where('tenant_id', $tenantId)->count() === 0,
            'status'     => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse quick-created successfully',
            'data'    => $warehouse,
        ], 201);
    }
}
