<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\StockTransferItem;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockTransferApiController extends Controller
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
     * GET /api/inventory/transfers/meta
     */
    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);
        [$tenantId] = $this->resolveTenantContext();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);
        $products   = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'   => ['Draft', 'Pending', 'In Transit', 'Completed', 'Cancelled'],
                'warehouses' => $warehouses,
                'products'   => $products,
            ],
        ]);
    }

    /**
     * GET /api/inventory/transfers
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);
        [$tenantId] = $this->resolveTenantContext();

        $query = StockTransfer::query()
            ->where('tenant_id', $tenantId)
            ->with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'creator:id,name']);

        if ($search = $request->input('search')) {
            $query->where('transfer_number', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($fromW = $request->input('from_warehouse_id')) {
            $query->where('from_warehouse_id', $fromW);
        }
        if ($toW = $request->input('to_warehouse_id')) {
            $query->where('to_warehouse_id', $toW);
        }

        $perPage   = min((int)$request->input('per_page', 15), 100);
        $transfers = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $transfers->items(),
            'meta'    => [
                'current_page' => $transfers->currentPage(),
                'last_page'    => $transfers->lastPage(),
                'per_page'     => $transfers->perPage(),
                'total'        => $transfers->total(),
            ],
        ]);
    }

    /**
     * POST /api/inventory/transfers
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);

        $validator = Validator::make($request->all(), [
            'from_warehouse_id' => ['required', 'integer', 'different:to_warehouse_id'],
            'to_warehouse_id'   => ['required', 'integer'],
            'transfer_date'     => ['required', 'date'],
            'status'            => ['nullable', 'in:Draft,Pending,In Transit,Completed,Cancelled'],
            'notes'             => ['nullable', 'string'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01'],
            'items.*.serial_numbers' => ['nullable'],
            'items.*.batch_id'   => ['nullable', 'integer'],
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

        // Verify available stock in source warehouse before creating transfer
        foreach ($validated['items'] as $item) {
            $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('product_id', $item['product_id'])
                ->where('warehouse_id', $validated['from_warehouse_id'])
                ->first();

            $reserved = \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
                ->where('product_id', $item['product_id'])
                ->where('warehouse_id', $validated['from_warehouse_id'])
                ->where('status', 'Active')
                ->sum('reserved_qty');

            $physicalQty = (float)($stock?->quantity ?? 0);
            $availableQty = max(0, $physicalQty - (float)$reserved);

            if ((float)$item['quantity'] > $availableQty) {
                $prod = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->find($item['product_id']);
                $name = $prod?->name ?? 'Item';
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient available stock for '{$name}'. Net Available: {$availableQty}, Requested: {$item['quantity']}."
                ], 422);
            }
        }

        $transfer = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId     = (StockTransfer::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $transferNo = 'TRF-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $st = StockTransfer::create([
                'tenant_id'         => $tenantId,
                'company_id'        => $companyId ?: null,
                'branch_id'         => $branchId ?: null,
                'transfer_number'   => $transferNo,
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id'   => $validated['to_warehouse_id'],
                'transfer_date'     => $validated['transfer_date'],
                'status'            => $validated['status'] ?? 'Draft',
                'notes'             => $validated['notes'] ?? null,
                'created_by'        => auth()->id() ?? 1,
            ]);

            foreach ($validated['items'] as $item) {
                $rawSerials = $item['serial_numbers'] ?? null;
                $serialArray = null;
                if (!empty($rawSerials)) {
                    $parts = is_array($rawSerials) ? $rawSerials : explode(',', (string)$rawSerials);
                    $resolved = [];
                    foreach ($parts as $p) {
                        $cleanSn = trim((string)$p);
                        if ($cleanSn === '') continue;

                        $matchedSn = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
                            ->where('product_id', $item['product_id'])
                            ->where('warehouse_id', $validated['from_warehouse_id'])
                            ->where(function($q) use ($cleanSn) {
                                $q->where('serial_number', $cleanSn)
                                  ->orWhere('serial_number', 'like', "%-{$cleanSn}")
                                  ->orWhere('serial_number', 'like', "%{$cleanSn}");
                            })
                            ->value('serial_number');

                        $resolved[] = $matchedSn ?: $cleanSn;
                    }
                    $serialArray = !empty($resolved) ? $resolved : null;
                }

                $stItem = StockTransferItem::create([
                    'stock_transfer_id' => $st->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'batch_id'          => $item['batch_id'] ?? null,
                    'serial_numbers'    => $serialArray,
                ]);

                // Reserve stock in source warehouse for this transfer
                \App\Domains\Inventory\Services\StockService::reserveStock(
                    tenantId: $tenantId,
                    productId: (int)$item['product_id'],
                    warehouseId: (int)$validated['from_warehouse_id'],
                    qty: (float)$item['quantity'],
                    referenceType: 'StockTransfer',
                    referenceId: $st->id,
                    referenceItemId: $stItem->id,
                    selectedSerials: $serialArray ?? []
                );
            }

            return $st->load(['items.product', 'fromWarehouse', 'toWarehouse']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock Transfer created successfully',
            'data'    => $transfer,
        ], 201);
    }

    /**
     * GET /api/inventory/transfers/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $transfer = StockTransfer::where('tenant_id', $tenantId)
            ->with(['fromWarehouse', 'toWarehouse', 'creator', 'items.product'])
            ->find($id);

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Transfer not found',
            ], 404);
        }

        $this->authorize('view', $transfer);

        return response()->json([
            'success' => true,
            'data'    => $transfer,
        ]);
    }

    /**
     * PATCH /api/inventory/transfers/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $transfer   = StockTransfer::where('tenant_id', $tenantId)->find($id);

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Transfer not found',
            ], 404);
        }

        $this->authorize('update', $transfer);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:Draft,Pending,In Transit,In-Transit,Completed,Cancelled'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $transfer->status = $request->input('status');
        if (in_array($transfer->status, ['Completed', 'completed'])) {
            $transfer->received_by = auth()->id();
        }
        $transfer->save();

        return response()->json([
            'success' => true,
            'message' => "Stock Transfer status updated to {$transfer->status}",
            'data'    => $transfer,
        ]);
    }

    /**
     * POST /api/inventory/transfers/{id}/dispatch
     */
    public function dispatch(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $transfer   = StockTransfer::where('tenant_id', $tenantId)->with('items')->find($id);

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Transfer not found',
            ], 404);
        }

        $this->authorize('update', $transfer);

        if (!in_array($transfer->status, ['Draft', 'Pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft or Pending transfers can be dispatched.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($transfer, $tenantId) {
                foreach ($transfer->items as $item) {
                    \App\Domains\Inventory\Services\StockService::recordOutflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: $transfer->from_warehouse_id,
                        quantity: (float)$item->quantity,
                        referenceType: 'StockTransfer',
                        referenceId: $transfer->id,
                        serialNumbers: $item->serial_numbers ?? []
                    );
                }

                $transfer->update([
                    'status'      => 'In-Transit',
                    'approved_by' => auth()->id() ?? 1,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock Transfer dispatched successfully and items are now In-Transit.',
            'data'    => $transfer->fresh(['fromWarehouse', 'toWarehouse', 'items.product']),
        ]);
    }

    /**
     * POST /api/inventory/transfers/{id}/receive
     */
    public function receive(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $transfer   = StockTransfer::where('tenant_id', $tenantId)->with('items')->find($id);

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Transfer not found',
            ], 404);
        }

        $this->authorize('update', $transfer);

        if (!in_array($transfer->status, ['In Transit', 'In-Transit'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only In-Transit transfers can be received.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($transfer, $tenantId) {
                foreach ($transfer->items as $item) {
                    $outTx = \App\Domains\Inventory\Models\StockTransaction::where('tenant_id', $tenantId)
                        ->where('reference_type', 'StockTransfer')
                        ->where('reference_id', $transfer->id)
                        ->where('product_id', $item->product_id)
                        ->where('type', 'OUT')
                        ->first();

                    $product  = Product::where('tenant_id', $tenantId)->find($item->product_id);
                    $unitCost = $outTx ? (float)$outTx->unit_cost : (float)($product ? $product->cost_price : 0);

                    $batch = $item->batch;
                    $batchNumber = $batch ? $batch->batch_number : null;
                    $mfgDate = $batch && $batch->manufacturing_date ? $batch->manufacturing_date->format('Y-m-d') : null;
                    $expiryDate = $batch && $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null;

                    \App\Domains\Inventory\Services\StockService::recordInflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: $transfer->to_warehouse_id,
                        quantity: (float)$item->quantity,
                        unitCost: $unitCost,
                        referenceType: 'StockTransfer',
                        referenceId: $transfer->id,
                        batchNumber: $batchNumber,
                        serialNumbers: $item->serial_numbers ?? [],
                        mfgDate: $mfgDate,
                        expiryDate: $expiryDate
                    );

                    $item->update(['received_quantity' => $item->quantity]);
                }

                $transfer->update([
                    'status'      => 'Completed',
                    'received_by' => auth()->id() ?? 1,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Receive failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock Transfer received successfully.',
            'data'    => $transfer->fresh(['fromWarehouse', 'toWarehouse', 'items.product']),
        ]);
    }

    /**
     * POST /api/inventory/transfers/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $transfer   = StockTransfer::where('tenant_id', $tenantId)->find($id);

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Transfer not found',
            ], 404);
        }

        $this->authorize('update', $transfer);

        if ($transfer->status === 'Completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a Completed transfer.',
            ], 422);
        }

        DB::transaction(function () use ($transfer, $tenantId) {
            if (in_array($transfer->status, ['In Transit', 'In-Transit'])) {
                foreach ($transfer->items as $item) {
                    $outTx = \App\Domains\Inventory\Models\StockTransaction::where('tenant_id', $tenantId)
                        ->where('reference_type', 'StockTransfer')
                        ->where('reference_id', $transfer->id)
                        ->where('product_id', $item->product_id)
                        ->where('type', 'OUT')
                        ->first();

                    $product = Product::where('tenant_id', $tenantId)->find($item->product_id);
                    $unitCost = $outTx ? (float)$outTx->unit_cost : (float)($product ? $product->cost_price : 0);
                    $batch = $item->batch;
                    $batchNumber = $batch ? $batch->batch_number : null;
                    $mfgDate = $batch && $batch->manufacturing_date ? $batch->manufacturing_date->format('Y-m-d') : null;
                    $expiryDate = $batch && $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null;

                    \App\Domains\Inventory\Services\StockService::recordInflow(
                        tenantId: $tenantId,
                        productId: (int)$item->product_id,
                        warehouseId: (int)$transfer->from_warehouse_id,
                        quantity: (float)$item->quantity,
                        unitCost: $unitCost,
                        referenceType: 'StockTransferReturn',
                        referenceId: $transfer->id,
                        batchNumber: $batchNumber,
                        serialNumbers: $item->serial_numbers ?? [],
                        mfgDate: $mfgDate,
                        expiryDate: $expiryDate
                    );
                }
            } else {
                foreach ($transfer->items as $item) {
                    \App\Domains\Inventory\Services\StockService::releaseStock(
                        tenantId: $tenantId,
                        productId: (int)$item->product_id,
                        warehouseId: (int)$transfer->from_warehouse_id,
                        qty: (float)$item->quantity,
                        referenceType: 'StockTransfer',
                        referenceId: $transfer->id,
                        referenceItemId: $item->id
                    );
                }
            }
            $transfer->update(['status' => 'Cancelled']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock Transfer cancelled successfully.',
            'data'    => $transfer,
        ]);
    }

    /**
     * GET /api/inventory/transfers/export
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', StockTransfer::class);
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\StockTransferExport($tenantId, $request->all()),
            'stock_transfers_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
