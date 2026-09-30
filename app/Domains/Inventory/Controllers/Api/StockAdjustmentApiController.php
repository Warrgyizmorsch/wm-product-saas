<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockAdjustmentItem;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Services\StockService;
use App\Exports\StockAdjustmentExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockAdjustmentApiController extends Controller
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
     * GET /api/inventory/adjustments/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $products   = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'unit_cost', 'cost_price']);

        return response()->json([
            'success' => true,
            'data'    => [
                'reasons'    => ['Damaged', 'Expired', 'Stock Count Variance', 'Theft/Loss', 'Scrap', 'Sample', 'Other'],
                'types'      => ['Addition', 'Deduction'],
                'statuses'   => ['Draft', 'Approved', 'Cancelled'],
                'warehouses' => $warehouses,
                'products'   => $products,
            ],
        ]);
    }

    /**
     * GET /api/inventory/adjustments
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = StockAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->with(['warehouse:id,name,code', 'creator:id,name', 'approver:id,name']);

        if ($search = $request->input('search')) {
            $query->where('adjustment_number', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($reason = $request->input('reason')) {
            $query->where('reason', $reason);
        }
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $perPage     = min((int)$request->input('per_page', 15), 100);
        $adjustments = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $adjustments->items(),
            'meta'    => [
                'current_page' => $adjustments->currentPage(),
                'last_page'    => $adjustments->lastPage(),
                'per_page'     => $adjustments->perPage(),
                'total'        => $adjustments->total(),
            ],
        ]);
    }

    /**
     * POST /api/inventory/adjustments
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warehouse_id'        => ['required', 'integer'],
            'adjustment_date'     => ['required', 'date'],
            'reason'              => ['required', 'string', 'max:255'],
            'notes'               => ['nullable', 'string'],
            'status'              => ['nullable', 'in:Draft,Approved,Cancelled,Completed'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer'],
            'items.*.type'        => ['required', 'in:Addition,Deduction,increase,decrease,set'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_id'    => ['nullable', 'integer'],
            'items.*.serial_numbers' => ['nullable'],
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

        $autoApprove = in_array(strtolower($validated['status'] ?? 'draft'), ['approved', 'completed']);

        $adjustment = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId, $autoApprove) {
            $adjNo = 'ADJ-' . strtoupper(uniqid());

            $adj = StockAdjustment::create([
                'tenant_id'         => $tenantId,
                'company_id'        => $companyId,
                'branch_id'         => $branchId,
                'warehouse_id'      => $validated['warehouse_id'],
                'adjustment_number' => $adjNo,
                'adjustment_date'   => $validated['adjustment_date'],
                'reason'            => $validated['reason'],
                'status'            => $autoApprove ? 'Approved' : 'Draft',
                'notes'             => $validated['notes'] ?? null,
                'created_by'        => auth()->id() ?? 1,
                'approved_by'       => $autoApprove ? (auth()->id() ?? 1) : null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::where('tenant_id', $tenantId)->find($item['product_id']);
                $qty  = (float)$item['quantity'];
                $cost = isset($item['unit_cost']) && $item['unit_cost'] > 0
                    ? (float)$item['unit_cost']
                    : (float)($product?->cost_price ?? $product?->unit_cost ?? 0);

                $normalizedType = match(strtolower($item['type'])) {
                    'addition', 'increase' => 'Addition',
                    default => 'Deduction',
                };

                $serials = !empty($item['serial_numbers']) 
                    ? (is_array($item['serial_numbers']) ? $item['serial_numbers'] : explode(',', $item['serial_numbers']))
                    : null;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adj->id,
                    'product_id'          => $item['product_id'],
                    'type'                => $normalizedType,
                    'quantity'            => $qty,
                    'unit_cost'           => $cost,
                    'total_amount'        => $qty * $cost,
                    'batch_id'            => $item['batch_id'] ?? null,
                    'serial_numbers'      => $serials,
                ]);

                if ($autoApprove) {
                    if ($normalizedType === 'Addition') {
                        StockService::recordInflow(
                            tenantId: $tenantId,
                            productId: $item['product_id'],
                            warehouseId: $adj->warehouse_id,
                            quantity: $qty,
                            unitCost: $cost,
                            referenceType: 'StockAdjustment',
                            referenceId: $adj->id,
                            serialNumbers: $serials ?? []
                        );
                    } else {
                        StockService::recordOutflow(
                            tenantId: $tenantId,
                            productId: $item['product_id'],
                            warehouseId: $adj->warehouse_id,
                            quantity: $qty,
                            referenceType: 'StockAdjustment',
                            referenceId: $adj->id,
                            serialNumbers: $serials ?? []
                        );
                    }
                }
            }

            return $adj->load(['items.product', 'warehouse', 'creator']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment created successfully',
            'data'    => $adjustment,
        ], 201);
    }

    /**
     * GET /api/inventory/adjustments/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $adj = StockAdjustment::where('tenant_id', $tenantId)
            ->with(['warehouse', 'creator', 'approver', 'items.product', 'items.batch'])
            ->find($id);

        if (!$adj) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Adjustment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $adj,
        ]);
    }

    /**
     * POST /api/inventory/adjustments/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $adjustment = StockAdjustment::where('tenant_id', $tenantId)->with('items')->find($id);

        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Adjustment not found',
            ], 404);
        }

        if ($adjustment->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft adjustments can be approved.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($adjustment, $tenantId) {
                foreach ($adjustment->items as $item) {
                    if ($item->type === 'Addition') {
                        StockService::recordInflow(
                            tenantId: $tenantId,
                            productId: $item->product_id,
                            warehouseId: $adjustment->warehouse_id,
                            quantity: (float)$item->quantity,
                            unitCost: (float)$item->unit_cost,
                            referenceType: 'StockAdjustment',
                            referenceId: $adjustment->id,
                            serialNumbers: $item->serial_numbers ?? []
                        );
                    } else {
                        StockService::recordOutflow(
                            tenantId: $tenantId,
                            productId: $item->product_id,
                            warehouseId: $adjustment->warehouse_id,
                            quantity: (float)$item->quantity,
                            referenceType: 'StockAdjustment',
                            referenceId: $adjustment->id,
                            serialNumbers: $item->serial_numbers ?? []
                        );
                    }
                }

                $adjustment->update([
                    'status'      => 'Approved',
                    'approved_by' => auth()->id() ?? 1,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Approval failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock Adjustment approved and inventory updated.',
            'data'    => $adjustment->fresh(['items.product', 'warehouse', 'creator', 'approver']),
        ]);
    }

    /**
     * POST /api/inventory/adjustments/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $adjustment = StockAdjustment::where('tenant_id', $tenantId)->find($id);

        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Adjustment not found',
            ], 404);
        }

        if ($adjustment->status === 'Approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel an approved adjustment.',
            ], 422);
        }

        $adjustment->update(['status' => 'Cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Stock Adjustment cancelled successfully.',
            'data'    => $adjustment,
        ]);
    }

    /**
     * GET /api/inventory/adjustments/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return Excel::download(
            new StockAdjustmentExport($tenantId, $request->all()),
            'stock_adjustments_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
