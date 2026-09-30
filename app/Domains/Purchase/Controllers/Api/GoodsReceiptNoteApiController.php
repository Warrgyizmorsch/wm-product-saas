<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GoodsReceiptNoteApiController extends Controller
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
     * GET /api/purchase/grns
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = GoodsReceiptNote::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name', 'purchaseOrder:id,purchase_order_number', 'warehouse:id,name']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                  ->orWhere('challan_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $grns    = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $grns->items(),
            'meta'    => [
                'current_page' => $grns->currentPage(),
                'last_page'    => $grns->lastPage(),
                'per_page'     => $grns->perPage(),
                'total'        => $grns->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/grns
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id'             => ['required', 'integer'],
            'warehouse_id'          => ['nullable', 'integer'],
            'purchase_order_id'     => ['nullable', 'integer'],
            'receipt_date'          => ['required', 'date'],
            'challan_number'        => ['nullable', 'string', 'max:100'],
            'challan_date'          => ['nullable', 'date'],
            'status'                => ['nullable', 'in:Draft,Received,Inspected,Rejected'],
            'notes'                 => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer'],
            'items.*.received_qty'  => ['required', 'numeric', 'min:0.01'],
            'items.*.accepted_qty'  => ['nullable', 'numeric', 'min:0'],
            'items.*.rejected_qty'  => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
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

        $grn = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId = (GoodsReceiptNote::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $grnNo  = 'GRN-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $grnModel = GoodsReceiptNote::create([
                'tenant_id'         => $tenantId,
                'company_id'        => $companyId,
                'branch_id'         => $branchId,
                'vendor_id'         => $validated['vendor_id'],
                'warehouse_id'      => $validated['warehouse_id'] ?? null,
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'grn_number'        => $grnNo,
                'received_date'     => $validated['received_date'] ?? $validated['receipt_date'] ?? now()->toDateString(),
                'challan_number'    => $validated['challan_number'] ?? null,
                'challan_date'      => $validated['challan_date'] ?? null,
                'status'            => $validated['status'] ?? 'Received',
                'notes'             => $validated['notes'] ?? null,
                'created_by'        => auth()->id() ?? 1,
            ]);

            foreach ($validated['items'] as $item) {
                GoodsReceiptNoteItem::create([
                    'tenant_id'             => $tenantId,
                    'company_id'            => $companyId,
                    'branch_id'             => $branchId,
                    'goods_receipt_note_id' => $grnModel->id,
                    'product_id'            => $item['product_id'],
                    'ordered_qty'           => $item['received_qty'],
                    'received_qty'          => $item['received_qty'],
                    'accepted_qty'          => $item['accepted_qty'] ?? $item['received_qty'],
                    'rejected_qty'          => $item['rejected_qty'] ?? 0,
                    'remaining_qty'         => 0,
                    'unit_rate'             => $item['unit_rate'] ?? $item['unit_price'] ?? 0,
                    'total_amount'          => ($item['received_qty'] * ($item['unit_rate'] ?? $item['unit_price'] ?? 0)),
                ]);
            }

            return $grnModel->load(['items.product', 'vendor', 'purchaseOrder']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Goods Receipt Note created successfully',
            'data'    => $grn,
        ], 201);
    }

    /**
     * GET /api/purchase/grns/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['vendor', 'purchaseOrder', 'warehouse', 'items.product'])
            ->find($id);

        if (!$grn) {
            return response()->json([
                'success' => false,
                'message' => 'Goods Receipt Note not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $grn,
        ]);
    }

    /**
     * PATCH /api/purchase/grns/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $grn        = GoodsReceiptNote::where('tenant_id', $tenantId)->find($id);

        if (!$grn) {
            return response()->json([
                'success' => false,
                'message' => 'Goods Receipt Note not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:Draft,Received,Inspected,Rejected'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $grn->status = $request->input('status');
        $grn->save();

        return response()->json([
            'success' => true,
            'message' => "GRN status updated to {$grn->status}",
            'data'    => $grn,
        ]);
    }

    /**
     * POST /api/purchase/grns/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $grn        = GoodsReceiptNote::where('tenant_id', $tenantId)->with('items')->find($id);

        if (!$grn) {
            return response()->json([
                'success' => false,
                'message' => 'Goods Receipt Note not found',
            ], 404);
        }

        if ($grn->status === 'Approved') {
            return response()->json([
                'success' => false,
                'message' => 'This GRN has already been approved.',
            ], 422);
        }

        $targetWhId = $grn->warehouse_id
            ?? Warehouse::where('tenant_id', $tenantId)->value('id')
            ?? Warehouse::value('id');

        if (!$targetWhId) {
            $defaultWh = Warehouse::create([
                'tenant_id' => $tenantId,
                'name'      => 'Main Warehouse',
                'code'      => 'WH-MAIN-' . rand(100, 999),
                'status'    => 'active',
            ]);
            $targetWhId = $defaultWh->id;
        }

        try {
            DB::transaction(function () use ($grn, $tenantId, $targetWhId) {
                foreach ($grn->items as $item) {
                    $qty = (float)($item->accepted_qty > 0 ? $item->accepted_qty : $item->received_qty);
                    if ($qty <= 0) continue;

                    \App\Domains\Inventory\Services\StockService::recordInflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: (int)$targetWhId,
                        quantity: $qty,
                        unitCost: (float)($item->unit_rate ?? $item->unit_price ?? 0),
                        referenceType: 'GoodsReceiptNote',
                        referenceId: $grn->id,
                        serialNumbers: !empty($item->serial_numbers) ? (is_array($item->serial_numbers) ? $item->serial_numbers : explode(',', $item->serial_numbers)) : []
                    );
                }

                $grn->update([
                    'status'       => 'Approved',
                    'warehouse_id' => $targetWhId,
                    'approved_by'  => auth()->id() ?? 1,
                    'approved_at'  => now(),
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve GRN: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Goods Receipt Note {$grn->grn_number} approved and inventory updated.",
            'data'    => $grn->fresh(['items.product', 'vendor', 'warehouse']),
        ]);
    }

    /**
     * GET /api/purchase/grns/pending
     */
    public function indexPending(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['Approved', 'Partially Received'])
            ->with(['vendor:id,name,company_name', 'items.product:id,name,sku']);

        $perPage = min((int)$request->input('per_page', 15), 100);
        $orders  = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/purchase/grns/get-po-items/{poId}
     */
    public function getPurchaseOrderItems(int $poId): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $po         = PurchaseOrder::where('tenant_id', $tenantId)->with('items.product')->find($poId);

        if (!$po) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order not found',
            ], 404);
        }

        $items = [];
        foreach ($po->items as $item) {
            $receivedSoFar = (float)GoodsReceiptNoteItem::where('purchase_order_item_id', $item->id)
                ->whereHas('goodsReceiptNote', fn($q) => $q->where('status', 'Approved'))
                ->sum('received_qty');

            $pendingQty = max(0, (float)$item->quantity - $receivedSoFar);

            $items[] = [
                'purchase_order_item_id' => $item->id,
                'product_id'             => $item->product_id,
                'product_name'           => $item->product?->name ?? 'Product #' . $item->product_id,
                'sku'                    => $item->product?->sku ?? '',
                'ordered_quantity'       => (float)$item->quantity,
                'received_quantity'      => $receivedSoFar,
                'pending_quantity'       => $pendingQty,
                'unit_price'             => (float)$item->unit_price,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'purchase_order' => [
                    'id'                    => $po->id,
                    'purchase_order_number' => $po->purchase_order_number,
                    'vendor_id'             => $po->vendor_id,
                ],
                'items' => $items,
            ],
        ]);
    }

    /**
     * GET /api/purchase/grns/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\GoodsReceiptNoteExport($tenantId, $request->all()),
            'grns_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
