<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SerialNumberApiController extends Controller
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
     * GET /api/inventory/serials
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = SerialNumber::query()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name,sku', 'warehouse:id,name,code', 'batch:id,batch_number']);

        if ($search = $request->input('search')) {
            $query->where('serial_number', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $perPage = min((int)$request->input('per_page', 20), 100);
        $serials = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $serials->items(),
            'meta'    => [
                'current_page' => $serials->currentPage(),
                'last_page'    => $serials->lastPage(),
                'per_page'     => $serials->perPage(),
                'total'        => $serials->total(),
            ],
        ]);
    }

    /**
     * POST /api/inventory/serials
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id'     => ['required', 'integer'],
            'warehouse_id'   => ['nullable', 'integer'],
            'batch_id'       => ['nullable', 'integer'],
            'serial_number'  => ['required', 'string', 'max:100'],
            'purchase_rate'  => ['nullable', 'numeric', 'min:0'],
            'status'         => ['nullable', 'in:Available,Reserved,Sold,Returned,Damaged,In Transit,Scrapped'],
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

        $serial = SerialNumber::create([
            'tenant_id'     => $tenantId,
            'company_id'    => $companyId,
            'branch_id'     => $branchId,
            'product_id'    => $validated['product_id'],
            'warehouse_id'  => $validated['warehouse_id'] ?? null,
            'batch_id'      => $validated['batch_id'] ?? null,
            'serial_number' => $validated['serial_number'],
            'purchase_rate' => $validated['purchase_rate'] ?? 0,
            'status'        => $validated['status'] ?? 'Available',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serial number created successfully',
            'data'    => $serial->load(['product', 'warehouse', 'batch']),
        ], 201);
    }

    /**
     * GET /api/inventory/serials/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $serial = SerialNumber::where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'batch', 'transactionIn', 'transactionOut'])
            ->find($id);

        if (!$serial) {
            return response()->json([
                'success' => false,
                'message' => 'Serial Number not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $serial,
        ]);
    }

    /**
     * GET /api/inventory/serials/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = SerialNumber::query()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name,sku', 'warehouse:id,name,code', 'batch:id,batch_number']);

        if ($search = $request->input('search')) {
            $query->where('serial_number', 'like', "%{$search}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $fileName = 'serial_numbers_export_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, ['ID', 'Serial Number', 'Product Name', 'Product SKU', 'Warehouse', 'Batch Number', 'Status', 'Purchase Rate', 'Created At']);

            $query->chunk(500, function ($serials) use ($handle) {
                foreach ($serials as $s) {
                    fputcsv($handle, [
                        $s->id,
                        $s->serial_number,
                        $s->product?->name ?? 'N/A',
                        $s->product?->sku ?? 'N/A',
                        $s->warehouse?->name ?? 'N/A',
                        $s->batch?->batch_number ?? 'N/A',
                        $s->status,
                        $s->purchase_rate,
                        $s->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * PUT/PATCH /api/inventory/serials/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $serial = SerialNumber::where('tenant_id', $tenantId)->find($id);

        if (!$serial) {
            return response()->json([
                'success' => false,
                'message' => 'Serial Number not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'warehouse_id'  => ['nullable', 'integer'],
            'batch_id'      => ['nullable', 'integer'],
            'serial_number' => ['sometimes', 'required', 'string', 'max:100'],
            'purchase_rate' => ['nullable', 'numeric', 'min:0'],
            'status'        => ['nullable', 'in:Available,Reserved,Sold,Returned,Damaged,In Transit,Scrapped'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $serial->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Serial number updated successfully',
            'data'    => $serial->fresh(['product', 'warehouse', 'batch']),
        ]);
    }

    /**
     * DELETE /api/inventory/serials/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $serial = SerialNumber::where('tenant_id', $tenantId)->find($id);

        if (!$serial) {
            return response()->json([
                'success' => false,
                'message' => 'Serial Number not found',
            ], 404);
        }

        $serial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Serial number deleted successfully',
        ]);
    }
}

