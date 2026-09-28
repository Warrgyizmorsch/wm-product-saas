<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseOrderApiController extends Controller
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
     * GET /api/purchase/orders/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $vendors    = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'company_name', 'email', 'phone', 'gstin']);
        $products   = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'cost_price', 'unit_cost', 'gst_rate']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'       => ['Draft', 'Approved', 'Completed', 'Cancelled'],
                'discount_types' => ['fixed', 'percentage'],
                'tax_types'      => ['exclusive', 'inclusive'],
                'gst_types'      => ['cgst_sgst', 'igst', 'none'],
                'freight_terms'  => ['Prepaid', 'To Pay', 'Free on Board (FOB)', 'Cost & Freight (CFR)', 'Ex-Works'],
                'vendors'        => $vendors,
                'products'       => $products,
                'warehouses'     => $warehouses,
            ],
        ]);
    }

    /**
     * GET /api/purchase/orders
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['vendor:id,name,company_name,email,phone', 'creator:id,name']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_order_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('date', '<=', $toDate);
        }

        if ($request->boolean('trashed_only')) {
            $query->onlyTrashed();
        }

        $sortBy  = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'purchase_order_number', 'date', 'delivery_date', 'status', 'grand_total', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $orders  = $query->paginate($perPage);

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
     * POST /api/purchase/orders
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vendor_id'                  => ['required', 'integer'],
            'date'                       => ['required', 'date'],
            'delivery_date'              => ['nullable', 'date'],
            'status'                     => ['nullable', 'in:Draft,Approved,Completed,Cancelled'],
            'reference'                  => ['nullable', 'string', 'max:100'],
            'supplier_quotation_number'  => ['nullable', 'string', 'max:100'],
            'discount_type'              => ['nullable', 'in:fixed,percentage'],
            'tax_type'                   => ['nullable', 'in:exclusive,inclusive'],
            'gst_type'                   => ['nullable', 'in:cgst_sgst,igst,none'],
            'freight_terms'              => ['nullable', 'string'],
            'freight_amount'             => ['nullable', 'numeric'],
            'notes'                      => ['nullable', 'string'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'integer'],
            'items.*.warehouse_id'       => ['nullable', 'integer'],
            'items.*.quantity'           => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'           => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'           => ['nullable', 'numeric', 'min:0'],
            'items.*.description'        => ['nullable', 'string'],
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

        $order = DB::transaction(function () use ($validated, $tenantId, $companyId, $branchId) {
            $lastId   = (PurchaseOrder::where('tenant_id', $tenantId)->max('id') ?? 0) + 1;
            $poNumber = 'PO-' . str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

            $subtotal    = 0;
            $totalTax    = 0;
            $itemRecords = [];

            foreach ($validated['items'] as $row) {
                $qty       = (float)$row['quantity'];
                $price     = (float)$row['unit_price'];
                $discount  = (float)($row['discount'] ?? 0);
                $taxRate   = (float)($row['tax_rate'] ?? 0);

                $lineSubtotal = max(0, ($qty * $price) - $discount);
                $lineTax      = $lineSubtotal * ($taxRate / 100);
                $lineTotal    = $lineSubtotal + $lineTax;

                $subtotal += $lineSubtotal;
                $totalTax += $lineTax;

                $itemRecords[] = [
                    'tenant_id'    => $tenantId,
                    'company_id'   => $companyId,
                    'branch_id'    => $branchId,
                    'product_id'   => $row['product_id'],
                    'warehouse_id' => $row['warehouse_id'] ?? null,
                    'description'  => $row['description'] ?? null,
                    'line_type'    => 'stock',
                    'quantity'     => $qty,
                    'rate'         => $price,
                    'amount'       => $lineSubtotal,
                    'tax_percent'  => $taxRate,
                    'tax_amount'   => $lineTax,
                    'total_amount' => $lineTotal,
                ];
            }

            $freight    = (float)($validated['freight_amount'] ?? 0);
            $grandTotal = $subtotal + $totalTax + $freight;

            $po = PurchaseOrder::create([
                'tenant_id'                 => $tenantId,
                'company_id'                => $companyId,
                'branch_id'                 => $branchId,
                'vendor_id'                 => $validated['vendor_id'],
                'purchase_order_number'     => $poNumber,
                'date'                      => $validated['date'],
                'delivery_date'             => $validated['delivery_date'] ?? null,
                'status'                    => $validated['status'] ?? 'Draft',
                'reference'                 => $validated['reference'] ?? null,
                'supplier_quotation_number' => $validated['supplier_quotation_number'] ?? null,
                'discount_type'             => $validated['discount_type'] ?? 'fixed',
                'tax_type'                  => $validated['tax_type'] ?? 'exclusive',
                'gst_type'                  => $validated['gst_type'] ?? 'cgst_sgst',
                'subtotal'                  => $subtotal,
                'tax_amount'                => $totalTax,
                'freight_terms'             => $validated['freight_terms'] ?? null,
                'freight_amount'            => $freight,
                'grand_total'               => $grandTotal,
                'notes'                     => $validated['notes'] ?? null,
                'created_by'                => auth()->id() ?? 1,
            ]);

            foreach ($itemRecords as $itemData) {
                $itemData['purchase_order_id'] = $po->id;
                PurchaseOrderItem::create($itemData);
            }

            return $po->load(['items.product', 'vendor']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order created successfully',
            'data'    => $order,
        ], 201);
    }

    /**
     * GET /api/purchase/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $order = PurchaseOrder::where('tenant_id', $tenantId)
            ->with([
                'vendor',
                'items.product',
                'goodsReceiptNotes',
                'vendorBills',
                'advancePayments',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    /**
     * PATCH /api/purchase/orders/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status'           => ['required', 'string', 'in:Draft,Approved,Completed,Cancelled'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $order->status = $request->input('status');
        if ($order->status === 'Cancelled') {
            $order->rejection_reason = $request->input('rejection_reason');
        } elseif ($order->status === 'Completed') {
            $order->completed_at = now();
        }
        $order->save();

        return response()->json([
            'success' => true,
            'message' => "Purchase order status updated to {$order->status}",
            'data'    => $order,
        ]);
    }

    /**
     * POST /api/purchase/orders/{id}/convert-to-grn
     */
    public function convertToGrn(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $order = PurchaseOrder::where('tenant_id', $tenantId)->with('items')->findOrFail($id);

        $grnNo = 'GRN-' . strtoupper(bin2hex(random_bytes(4)));

        $targetWhId = $request->input('warehouse_id')
            ?? $order->warehouse_id
            ?? \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->value('id')
            ?? \App\Domains\Inventory\Models\Warehouse::value('id');

        if (!$targetWhId) {
            $defaultWh = \App\Domains\Inventory\Models\Warehouse::create([
                'tenant_id' => $tenantId,
                'name'      => 'Main Warehouse',
                'code'      => 'WH-MAIN-' . rand(100, 999),
                'status'    => 'active',
            ]);
            $targetWhId = $defaultWh->id;
        }

        $grn = DB::transaction(function () use ($order, $grnNo, $tenantId, $companyId, $branchId, $request, $targetWhId) {
            $grnModel = GoodsReceiptNote::create([
                'tenant_id'          => $tenantId,
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'purchase_order_id'  => $order->id,
                'vendor_id'          => $order->vendor_id,
                'grn_number'         => $grnNo,
                'received_date'      => $request->input('received_date') ?? $request->input('receipt_date') ?? now()->toDateString(),
                'status'             => 'Received',
                'warehouse_id'       => $targetWhId,
                'challan_number'     => $request->input('challan_number'),
                'challan_date'       => $request->input('challan_date'),
                'notes'              => $request->input('notes'),
            ]);

            foreach ($order->items as $item) {
                GoodsReceiptNoteItem::create([
                    'tenant_id'              => $tenantId,
                    'company_id'             => $companyId,
                    'branch_id'              => $branchId,
                    'goods_receipt_note_id'  => $grnModel->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id'             => $item->product_id,
                    'ordered_qty'            => $item->quantity,
                    'received_qty'           => $item->quantity,
                    'accepted_qty'           => $item->quantity,
                    'rejected_qty'           => 0,
                    'remaining_qty'          => 0,
                    'unit_rate'              => (float)($item->rate ?? $item->unit_price ?? 0),
                    'total_amount'           => (float)($item->quantity * ($item->rate ?? $item->unit_price ?? 0)),
                ]);
            }

            return $grnModel->load(['items.product', 'vendor', 'purchaseOrder']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Goods Receipt Note (GRN) created successfully from Purchase Order',
            'data'    => $grn,
        ], 201);
    }

    /**
     * POST /api/purchase/orders/{id}/convert-to-bill
     */
    public function convertToBill(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $order = PurchaseOrder::where('tenant_id', $tenantId)->with('items')->findOrFail($id);

        $billNo = 'BILL-' . strtoupper(bin2hex(random_bytes(4)));

        $bill = DB::transaction(function () use ($order, $billNo, $tenantId, $companyId, $branchId, $request) {
            $vb = VendorBill::create([
                'tenant_id'         => $tenantId,
                'company_id'        => $companyId,
                'branch_id'         => $branchId,
                'vendor_id'         => $order->vendor_id,
                'purchase_order_id' => $order->id,
                'bill_number'       => $billNo,
                'vendor_invoice_no' => $request->input('vendor_invoice_no', $order->supplier_quotation_number),
                'bill_date'         => $request->input('bill_date', now()->toDateString()),
                'due_date'          => $request->input('due_date', now()->addDays(30)->toDateString()),
                'status'            => 'Draft',
                'subtotal'          => $order->subtotal,
                'tax_amount'        => $order->tax_amount,
                'total_amount'      => $order->grand_total,
                'amount_paid'       => 0,
                'balance_due'       => $order->grand_total,
                'notes'             => 'Generated from PO #' . $order->purchase_order_number,
            ]);

            foreach ($order->items as $item) {
                VendorBillItem::create([
                    'tenant_id'      => $tenantId,
                    'company_id'     => $companyId,
                    'branch_id'      => $branchId,
                    'vendor_bill_id' => $vb->id,
                    'product_id'     => $item->product_id,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    'tax_rate'       => $item->tax_rate,
                    'tax_amount'     => $item->tax_amount,
                    'subtotal'       => $item->subtotal,
                    'total_amount'   => $item->total_amount,
                ]);
            }

            return $vb->load(['items.product', 'vendor']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Vendor Bill created successfully from Purchase Order',
            'data'    => $bill,
        ], 201);
    }

    /**
     * DELETE /api/purchase/orders/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order deleted successfully',
        ]);
    }

    /**
     * POST /api/purchase/orders/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)->onlyTrashed()->findOrFail($id);

        $order->restore();

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order restored successfully',
            'data'    => $order,
        ]);
    }

    /**
     * POST /api/purchase/orders/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft Purchase Orders can be approved.',
            ], 422);
        }

        $order->update([
            'status'      => 'Approved',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order approved successfully.',
            'data'    => $order->fresh(['items.product', 'vendor']),
        ]);
    }

    /**
     * POST /api/purchase/orders/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft Purchase Orders can be rejected.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $order->update([
            'status'           => 'Cancelled',
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order rejected.',
            'data'    => $order,
        ]);
    }

    /**
     * POST /api/purchase/orders/{id}/remind
     */
    public function remind(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $order      = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Reminders can only be sent for pending Draft Purchase Orders.',
            ], 422);
        }

        \App\Domains\Purchase\Models\ApprovalReminder::create([
            'tenant_id'       => $tenantId,
            'remindable_type' => get_class($order),
            'remindable_id'   => $order->id,
            'user_id'         => auth()->id() ?? 1,
            'note'            => $request->input('note'),
        ]);

        $order->increment('reminder_count');
        $order->update(['last_reminded_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Reminder successfully recorded for PO #{$order->purchase_order_number}.",
            'data'    => $order,
        ]);
    }

    /**
     * GET /api/purchase/orders/approvals
     */
    public function poApprovals(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('status', 'Draft')
            ->with(['vendor:id,name,company_name', 'creator:id,name', 'items.product:id,name,sku']);

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
     * GET /api/purchase/orders/get-requisition-items
     */
    public function getRequisitionItems(Request $request): JsonResponse
    {
        [$tenantId]    = $this->resolveTenantContext();
        $requisitionId = (int)$request->query('requisition_id');
        $requisition   = \App\Domains\Purchase\Models\PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['items.product'])
            ->find($requisitionId);

        if (!$requisition) {
            return response()->json(['success' => false, 'message' => 'Requisition not found.'], 404);
        }

        $items = [];
        foreach ($requisition->items as $item) {
            $items[] = [
                'product_id'     => $item->product_id,
                'product_name'   => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                'quantity'       => (float)$item->quantity,
                'unit_price'     => (float)($item->estimated_cost ?: ($item->product->cost_price ?? 0)),
                'tax_rate'       => (float)($item->product->tax_rate ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * GET /api/purchase/orders/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PurchaseOrderExport($tenantId, $request->all()),
            'purchase_orders_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
