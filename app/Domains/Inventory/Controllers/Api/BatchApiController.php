<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class BatchApiController extends Controller
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
     * GET /api/inventory/batches
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Batch::query()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name,sku', 'warehouse:id,name,code']);

        if ($search = $request->input('search')) {
            $query->where('batch_number', 'like', "%{$search}%");
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($request->boolean('available_only')) {
            $query->where('available_qty', '>', 0);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $batches = $query->orderBy('expiry_date', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $batches->items(),
            'meta'    => [
                'current_page' => $batches->currentPage(),
                'last_page'    => $batches->lastPage(),
                'per_page'     => $batches->perPage(),
                'total'        => $batches->total(),
            ],
        ]);
    }

    /**
     * POST /api/inventory/batches
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id'         => ['required', 'integer'],
            'warehouse_id'       => ['required', 'integer'],
            'batch_number'       => ['required', 'string', 'max:100'],
            'quantity'           => ['required', 'numeric', 'min:0.01'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date'        => ['nullable', 'date', 'after_or_equal:manufacturing_date'],
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

        $batch = Batch::create([
            'tenant_id'          => $tenantId,
            'company_id'         => $companyId,
            'branch_id'          => $branchId,
            'product_id'         => $validated['product_id'],
            'warehouse_id'       => $validated['warehouse_id'],
            'batch_number'       => $validated['batch_number'],
            'quantity'           => $validated['quantity'],
            'available_qty'      => $validated['quantity'],
            'manufacturing_date' => $validated['manufacturing_date'] ?? null,
            'expiry_date'        => $validated['expiry_date'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch created successfully',
            'data'    => $batch->load(['product', 'warehouse']),
        ], 201);
    }

    /**
     * GET /api/inventory/batches/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $batch = Batch::where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'serialNumbers'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $batch,
        ]);
    }

    /**
     * GET /api/inventory/batches/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BatchExport($tenantId, $request->all()),
            'batches_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * PUT/PATCH /api/inventory/batches/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $batch = Batch::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'batch_number'       => ['sometimes', 'required', 'string', 'max:100'],
            'warehouse_id'       => ['sometimes', 'required', 'integer'],
            'quantity'           => ['nullable', 'numeric', 'min:0'],
            'available_qty'      => ['nullable', 'numeric', 'min:0'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date'        => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $batch->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Batch updated successfully',
            'data'    => $batch->fresh(['product', 'warehouse']),
        ]);
    }

    /**
     * DELETE /api/inventory/batches/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $batch = Batch::where('tenant_id', $tenantId)->findOrFail($id);
        $batch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully',
        ]);
    }
}

