<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderRepository $orderRepo,
        protected PurchaseOrderService $orderService
    ) {}

    public function index(Request $request)
    {
        $orders = $this->orderRepo->getPaginatedOrders($request->all(), 10);
        return view('modules.purchase.orders.index', compact('orders'));
    }

    public function poApprovals(Request $request)
    {
        $orders = $this->orderRepo->getPaginatedOrders(array_merge($request->all(), ['status' => 'Draft']), 15);
        return view('modules.purchase.approvals.po-index', compact('orders'));
    }

    public function poDetailPartial(PurchaseOrder $order)
    {
        $order->load(['vendor', 'requisition', 'items.product']);
        return view('modules.purchase.approvals.po-detail-partial', compact('order'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)->where('status', 'Approved')->get();

        $selectedRequisitionId = $request->input('requisition_id');
        $requisitionItemIds = (array) $request->input('requisition_item_ids', []);
        $prefilledItems = [];

        // If specific PR item IDs were passed (e.g. from Pending PR page or Convert to PO button)
        if (!empty($requisitionItemIds)) {
            $prItems = \App\Domains\Purchase\Models\PurchaseRequisitionItem::where('tenant_id', $tenantId)
                ->whereIn('id', $requisitionItemIds)
                ->with(['product', 'warehouse', 'requisition'])
                ->get();

            $mergedItems = [];
            foreach ($prItems as $prItem) {
                if (!$selectedRequisitionId && $prItem->purchase_requisition_id) {
                    $selectedRequisitionId = $prItem->purchase_requisition_id;
                }

                $alreadyOrderedQty = (float) $prItem->ordered_qty;
                $poOrderedQty = (float) \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                    ->where('product_id', $prItem->product_id)
                    ->whereHas('order', function ($q) use ($prItem) {
                        $q->where('purchase_requisition_id', $prItem->purchase_requisition_id)
                          ->where('status', 'Approved');
                    })
                    ->sum('quantity');

                $alreadyOrderedQty = max($alreadyOrderedQty, $poOrderedQty);
                $pendingQty = max(0.0, (float) $prItem->quantity - $alreadyOrderedQty);
                if ($pendingQty <= 0) {
                    continue;
                }

                $productId = $prItem->product_id;
                $product   = $prItem->product;
                $costRate  = (float) ($prItem->estimated_cost ?: ($product?->cost_price ?: ($product?->unit_cost ?: 0.00)));

                if (isset($mergedItems[$productId])) {
                    $mergedItems[$productId]['quantity'] += $pendingQty;
                    $mergedItems[$productId]['requisition_item_ids'][] = $prItem->id;
                } else {
                    $mergedItems[$productId] = [
                        'requisition_item_id'  => $prItem->id,
                        'requisition_item_ids' => [$prItem->id],
                        'product_id'           => $productId,
                        'product_name'         => $product?->name ?? 'Product #' . $productId,
                        'quantity'             => $pendingQty,
                        'rate'                 => $costRate,
                        'warehouse_id'         => $prItem->warehouse_id,
                    ];
                }
            }
            $prefilledItems = array_values($mergedItems);
        } elseif ($selectedRequisitionId) {
            $prItems = \App\Domains\Purchase\Models\PurchaseRequisitionItem::where('tenant_id', $tenantId)
                ->where('purchase_requisition_id', $selectedRequisitionId)
                ->with(['product', 'warehouse'])
                ->get();

            $mergedItems = [];
            foreach ($prItems as $prItem) {
                $alreadyOrderedQty = (float) $prItem->ordered_qty;
                $poOrderedQty = (float) \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                    ->where('product_id', $prItem->product_id)
                    ->whereHas('order', function ($q) use ($prItem) {
                        $q->where('purchase_requisition_id', $prItem->purchase_requisition_id)
                          ->where('status', 'Approved');
                    })
                    ->sum('quantity');

                $alreadyOrderedQty = max($alreadyOrderedQty, $poOrderedQty);
                $pendingQty = max(0.0, (float) $prItem->quantity - $alreadyOrderedQty);
                if ($pendingQty <= 0) {
                    continue;
                }

                $productId = $prItem->product_id;
                $product   = $prItem->product;
                $costRate  = (float) ($prItem->estimated_cost ?: ($product?->cost_price ?: ($product?->unit_cost ?: 0.00)));

                if (isset($mergedItems[$productId])) {
                    $mergedItems[$productId]['quantity'] += $pendingQty;
                    $mergedItems[$productId]['requisition_item_ids'][] = $prItem->id;
                } else {
                    $mergedItems[$productId] = [
                        'requisition_item_id'  => $prItem->id,
                        'requisition_item_ids' => [$prItem->id],
                        'product_id'           => $productId,
                        'product_name'         => $product?->name ?? 'Product #' . $productId,
                        'quantity'             => $pendingQty,
                        'rate'                 => $costRate,
                        'warehouse_id'         => $prItem->warehouse_id,
                    ];
                }

                $requisitionItemIds[] = $prItem->id;
            }
            $prefilledItems = array_values($mergedItems);
        }

        $prefilledExpectedDate = null;
        if ($selectedRequisitionId) {
            $selectedReq = PurchaseRequisition::find($selectedRequisitionId);
            $prefilledExpectedDate = $selectedReq?->expected_date ? $selectedReq->expected_date->format('Y-m-d') : null;
        }

        return view('modules.purchase.orders.create', compact(
            'vendors',
            'products',
            'warehouses',
            'requisitions',
            'selectedRequisitionId',
            'requisitionItemIds',
            'prefilledItems',
            'prefilledExpectedDate'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'date' => 'nullable|date',
            'po_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'location' => 'nullable|string',
            'reference' => 'nullable|string',
            'supplier_quotation_number' => 'nullable|string',
            'purchase_requisition_id' => 'nullable|integer',
            'payment_terms' => 'nullable|string|max:255',
            'discount_type' => 'nullable|string',
            'tax_type' => 'nullable|string',
            'gst_type' => 'nullable|string',
            'freight_terms' => 'nullable|string',
            'freight_amount' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'cgst_amount' => 'nullable|numeric|min:0',
            'sgst_amount' => 'nullable|numeric|min:0',
            'igst_amount' => 'nullable|numeric|min:0',
            'grand_total' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.cgst_percent' => 'nullable|numeric|min:0',
            'items.*.sgst_percent' => 'nullable|numeric|min:0',
            'items.*.igst_percent' => 'nullable|numeric|min:0',
            'items.*.cgst_amount' => 'nullable|numeric|min:0',
            'items.*.sgst_amount' => 'nullable|numeric|min:0',
            'items.*.igst_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'nullable|numeric|min:0',
        ]);

        $po = $this->orderService->storeOrder($validated, $tenantId);

        return redirect()->route('purchase.orders.show', $po->id)
            ->with('success', "Purchase Order {$po->po_number} created successfully.");
    }

    public function show(int $id)
    {
        $order = $this->orderRepo->findWithDetails($id);
        return view('modules.purchase.orders.show', compact('order'));
    }

    public function detailPartial(int $id)
    {
        $order = $this->orderRepo->findWithDetails($id);
        return view('modules.purchase.orders.detail-partial', compact('order'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();
        $order = $this->orderRepo->findWithDetails($id);

        if ($order->status !== 'Draft') {
            return redirect()->route('purchase.orders.show', $id)
                ->with('error', 'Only Draft Purchase Orders can be edited.');
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)->where('status', 'Approved')->get();

        return view('modules.purchase.orders.edit', compact('order', 'vendors', 'products', 'warehouses', 'requisitions'));
    }

    public function update(Request $request, int $id)
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404);

        if ($order->status !== 'Draft') {
            return redirect()->route('purchase.orders.show', $id)
                ->with('error', 'Only Draft Purchase Orders can be updated.');
        }

        $validated = $request->validate([
            'vendor_id' => 'required|integer|exists:vendors,id',
            'po_date' => 'nullable|date',
            'date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date',
            'payment_terms' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $this->orderRepo->update($order, $validated);

        return redirect()->route('purchase.orders.show', $id)
            ->with('success', 'Purchase Order updated successfully.');
    }

    public function confirm(int $id)
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404);

        if ($order->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft Purchase Orders can be confirmed.');
        }

        $this->orderService->confirmOrder($order);

        return redirect()->back()->with('success', "Purchase Order {$order->purchase_order_number} has been approved successfully.");
    }

    public function approve(int $id)
    {
        return $this->confirm($id);
    }

    public function reject(Request $request, int $id)
    {
        return $this->cancel($request, $id);
    }

    public function cancel(Request $request, int $id)
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404);

        if (in_array($order->status, ['Completed', 'Cancelled'])) {
            return redirect()->back()->with('error', 'This Purchase Order cannot be cancelled.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $this->orderService->cancelOrder($order);
        $this->orderRepo->update($order, [
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->back()->with('success', "Purchase Order {$order->purchase_order_number} has been rejected/cancelled.");
    }

    public function downloadPdf(int $id)
    {
        $order = $this->orderRepo->findWithDetails($id);
        $pdf = Pdf::loadView('modules.purchase.orders.pdf', compact('order'));
        return $pdf->download("PO_{$order->po_number}.pdf");
    }

    public function destroy(int $id)
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404);

        if ($order->status !== 'Draft') {
            return redirect()->route('purchase.orders.show', $id)
                ->with('error', 'Only Draft Purchase Orders can be deleted.');
        }

        $this->orderRepo->delete($order);

        return redirect()->route('purchase.orders.index')
            ->with('success', 'Purchase Order deleted successfully.');
    }

    public function getRequisitionItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $requisitionId = (int) $request->query('requisition_id');
        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['items.product', 'items.warehouse'])
            ->find($requisitionId);

        if (!$requisition) {
            return response()->json(['success' => false, 'error' => 'Requisition not found.'], 404);
        }

        $items = [];
        foreach ($requisition->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                'quantity' => (float)$item->quantity,
                'estimated_cost' => (float)$item->estimated_cost,
            ];
        }

        return response()->json([
            'success' => true,
            'requisition_number' => $requisition->requisition_number,
            'expected_date' => $requisition->expected_date ? $requisition->expected_date->format('Y-m-d') : null,
            'items' => $items,
        ]);
    }

    public function remind(Request $request, int $id)
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404);

        if ($order->status !== 'Draft') {
            return redirect()->back()->with('error', 'Reminders can only be sent for pending Purchase Orders.');
        }

        if ($order->last_reminded_at && $order->last_reminded_at->diffInSeconds(now()) < 900) {
            $secondsLeft = 900 - $order->last_reminded_at->diffInSeconds(now());
            if ($secondsLeft >= 60) {
                $mins = (int)ceil($secondsLeft / 60);
                $timeStr = "{$mins} minute" . ($mins > 1 ? 's' : '');
            } else {
                $secs = max(1, (int)$secondsLeft);
                $timeStr = "{$secs} second" . ($secs > 1 ? 's' : '');
            }
            return redirect()->back()->with('error', "Reminder already sent recently! Please wait {$timeStr} before sending another reminder.");
        }

        $note = $request->input('note');

        \App\Domains\Purchase\Models\ApprovalReminder::create([
            'tenant_id' => require_tenant_id(),
            'remindable_type' => get_class($order),
            'remindable_id' => $order->id,
            'user_id' => auth()->id(),
            'note' => $note,
        ]);

        $order->increment('reminder_count');
        $order->update(['last_reminded_at' => now()]);

        return redirect()->back()->with('success', "Quick reminder successfully sent to approvers for PO #{$order->purchase_order_number}!");
    }
}
