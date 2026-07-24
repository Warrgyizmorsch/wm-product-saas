<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'requisition', 'creator']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('purchase_order_number', 'like', $search)
                  ->orWhere('reference', 'like', $search)
                  ->orWhereHas('vendor', function ($v) use ($search) {
                      $v->where('name', 'like', $search);
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'purchase_order_number', 'date', 'grand_total', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $orders = $query->paginate(10)->withQueryString();

        return view('modules.purchase.orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();
        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->get();

        $allRequisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->with('items')
            ->get();

        $requisitions = $allRequisitions->filter(function ($pr) use ($tenantId) {
            $existingPos = PurchaseOrder::where('tenant_id', $tenantId)
                ->where('purchase_requisition_id', $pr->id)
                ->where('status', '!=', 'Cancelled')
                ->with('items')
                ->get();

            foreach ($pr->items as $item) {
                $alreadyOrderedQty = 0.0;
                foreach ($existingPos as $po) {
                    $alreadyOrderedQty += (float) $po->items->where('product_id', $item->product_id)->sum('quantity');
                }
                $pendingQty = (float) $item->quantity - $alreadyOrderedQty;
                if ($pendingQty > 0.0001) {
                    return true;
                }
            }
            return false;
        });

        $selectedRequisitionId = $request->input('requisition_id');
        $requisitionItemIds = $request->input('requisition_item_ids', []);
        $prefilledItems = [];

        if ($selectedRequisitionId) {
            $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
                ->with(['items.product', 'items.warehouse'])
                ->find($selectedRequisitionId);
            
            if ($requisition) {
                $existingPos = PurchaseOrder::where('tenant_id', $tenantId)
                    ->where('purchase_requisition_id', $selectedRequisitionId)
                    ->where('status', '!=', 'Cancelled')
                    ->with('items')
                    ->get();

                foreach ($requisition->items as $item) {
                    $alreadyOrderedQty = 0.0;
                    foreach ($existingPos as $po) {
                        $alreadyOrderedQty += (float) $po->items->where('product_id', $item->product_id)->sum('quantity');
                    }
                    $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

                    if ($pendingQty > 0.0001) {
                        $prefilledItems[] = [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                            'quantity' => $pendingQty,
                            'warehouse_id' => $item->warehouse_id,
                            'warehouse_name' => $item->warehouse->name ?? '—',
                            'rate' => (float)($item->product->unit_cost ?? $item->estimated_cost ?? 0.00),
                            'estimated_cost' => (float)$item->estimated_cost,
                        ];
                    }
                }
            }
        } elseif (!empty($requisitionItemIds)) {
            $items = PurchaseRequisitionItem::whereIn('id', $requisitionItemIds)
                ->with(['product', 'warehouse', 'requisition'])
                ->get();

            if ($items->isNotEmpty()) {
                $requisitionIds = $items->pluck('purchase_requisition_id')->unique()->toArray();
                
                // If items are selected from different PRs, do not select any PR (leave it blank)
                $selectedRequisitionId = count($requisitionIds) === 1 ? $requisitionIds[0] : null;

                $groupedPrefilled = [];
                foreach ($items as $item) {
                    $alreadyOrderedQty = (float) $item->ordered_qty;

                    $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

                    if ($pendingQty > 0.0001) {
                        $prodId = $item->product_id;
                        if (isset($groupedPrefilled[$prodId])) {
                            $groupedPrefilled[$prodId]['quantity'] += $pendingQty;
                        } else {
                            $groupedPrefilled[$prodId] = [
                                'product_id' => $item->product_id,
                                'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                                'quantity' => $pendingQty,
                                'warehouse_id' => $item->warehouse_id,
                                'warehouse_name' => $item->warehouse->name ?? '—',
                                'rate' => (float)($item->product->unit_cost ?? $item->estimated_cost ?? 0.00),
                                'estimated_cost' => (float)$item->estimated_cost,
                            ];
                        }
                    }
                }
                $prefilledItems = array_values($groupedPrefilled);
            }
        }

        return view('modules.purchase.orders.create', compact('vendors', 'warehouses', 'products', 'requisitions', 'selectedRequisitionId', 'prefilledItems', 'requisitionItemIds'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'source_type' => 'nullable|string|in:direct,requisition,rfq',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:date',
            'purchase_requisition_id' => 'nullable|integer|exists:purchase_requisitions,id',
            'location' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'supplier_quotation_number' => 'nullable|string|max:255',
            'discount_type' => 'required|string|in:without_discount,item_wise,order_wise',
            'tax_type' => 'required|string|in:without_tax,item_wise_tax,order_wise_tax',
            'gst_type' => 'required|string|in:cgst_sgst,igst',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'cgst_amount' => 'required|numeric|min:0',
            'sgst_amount' => 'required|numeric|min:0',
            'igst_amount' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
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

        return DB::transaction(function () use ($validated, $tenantId, $request) {
            // Generate sequence number YYYY-000001
            $year = now()->format('Y');
            $prefix = "PO-{$year}-";
            $latest = PurchaseOrder::where('tenant_id', $tenantId)
                ->where('purchase_order_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->purchase_order_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $poNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $requisitionItemIds = $request->input('requisition_item_ids', []);
            $prItems = collect();
            if (!empty($requisitionItemIds)) {
                $prItems = PurchaseRequisitionItem::whereIn('id', $requisitionItemIds)->get();
            }

            // Determine if the selected items are from a single PR or multiple
            $prIds = $prItems->pluck('purchase_requisition_id')->unique();
            $headerPrId = $prIds->count() === 1 ? $prIds->first() : null;

            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $poNumber,
                'purchase_requisition_id' => $headerPrId ?: ($validated['purchase_requisition_id'] ?? null),
                'source_type' => $validated['source_type'] ?? (!empty($headerPrId) || !empty($validated['purchase_requisition_id']) ? 'requisition' : 'direct'),
                'vendor_id' => $validated['vendor_id'],
                'location' => $validated['location'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'supplier_quotation_number' => $validated['supplier_quotation_number'] ?? null,
                'date' => $validated['date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'discount_type' => $validated['discount_type'],
                'tax_type' => $validated['tax_type'],
                'gst_type' => $validated['gst_type'],
                'subtotal' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'],
                'cgst_amount' => $validated['cgst_amount'],
                'sgst_amount' => $validated['sgst_amount'],
                'igst_amount' => $validated['igst_amount'],
                'tax_amount' => $validated['tax_amount'],
                'grand_total' => $validated['grand_total'],
                'status' => 'Draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                $productId = $item['product_id'];
                $totalQty = (float)$item['quantity'];

                // Find matching PR items for this product
                $matchingPrItems = $prItems->where('product_id', $productId)->sortBy('id');

                $allocations = [];
                if ($matchingPrItems->isNotEmpty()) {
                    $remainingQty = $totalQty;

                    foreach ($matchingPrItems as $prItem) {
                        if ($remainingQty <= 0) break;

                        // Calculate pending quantity for this specific PR item
                        $alreadyOrdered = (float)$prItem->ordered_qty;
                        $pending = max(0.0, (float)$prItem->quantity - $alreadyOrdered);

                        if ($pending > 0.0001) {
                            $qtyToAlloc = min($remainingQty, $pending);
                            $remainingQty -= $qtyToAlloc;

                            // Add to allocations JSON
                            $allocations[] = [
                                'pr_item_id' => $prItem->id,
                                'quantity' => $qtyToAlloc,
                            ];
                        }
                    }

                    // Save any remaining/excess quantity without incrementing any PR (direct excess)
                    if ($remainingQty > 0.0001) {
                        $allocations[] = [
                            'pr_item_id' => null,
                            'quantity' => $remainingQty,
                        ];
                    }
                }

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $productId,
                    'requisition_item_allocations' => empty($allocations) ? null : $allocations,
                    'quantity' => $totalQty,
                    'rate' => $item['rate'],
                    'amount' => $item['amount'],
                    'discount_percent' => $item['discount_percent'] ?? 0.00,
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'tax_percent' => $item['tax_percent'] ?? 0.00,
                    'cgst_percent' => $item['cgst_percent'] ?? 0.00,
                    'sgst_percent' => $item['sgst_percent'] ?? 0.00,
                    'igst_percent' => $item['igst_percent'] ?? 0.00,
                    'cgst_amount' => $item['cgst_amount'] ?? 0.00,
                    'sgst_amount' => $item['sgst_amount'] ?? 0.00,
                    'igst_amount' => $item['igst_amount'] ?? 0.00,
                    'tax_amount' => $item['tax_amount'] ?? 0.00,
                    'total_amount' => $item['total_amount'] ?? $item['amount'],
                ]);
            }

            return redirect()->route('purchase.orders.show', $po->id)
                ->with('success', "Purchase Order {$poNumber} created successfully.");
        });
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'requisition', 'creator', 'items.product', 'warehouse'])
            ->findOrFail($id);

        return view('modules.purchase.orders.show', compact('order'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)
            ->with('items')
            ->findOrFail($id);

        if ($order->status !== 'Draft') {
            return redirect()->route('purchase.orders.show', $id)
                ->with('error', 'Only Draft Purchase Orders can be edited.');
        }

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->get();
        $allRequisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->with('items')
            ->get();

        $requisitions = $allRequisitions->filter(function ($pr) use ($tenantId, $order) {
            if ($order->purchase_requisition_id == $pr->id) {
                return true;
            }

            $existingPos = PurchaseOrder::where('tenant_id', $tenantId)
                ->where('purchase_requisition_id', $pr->id)
                ->where('status', '!=', 'Cancelled')
                ->with('items')
                ->get();

            foreach ($pr->items as $item) {
                $alreadyOrderedQty = 0.0;
                foreach ($existingPos as $po) {
                    $alreadyOrderedQty += (float) $po->items->where('product_id', $item->product_id)->sum('quantity');
                }
                $pendingQty = (float) $item->quantity - $alreadyOrderedQty;
                if ($pendingQty > 0.0001) {
                    return true;
                }
            }
            return false;
        });

        return view('modules.purchase.orders.edit', compact('order', 'vendors', 'warehouses', 'products', 'requisitions'));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status !== 'Draft') {
            return redirect()->route('purchase.orders.show', $id)
                ->with('error', 'Only Draft Purchase Orders can be updated.');
        }

        $validated = $request->validate([
            'vendor_id' => 'required|integer|exists:vendors,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:date',
            'purchase_requisition_id' => 'nullable|integer|exists:purchase_requisitions,id',
            'location' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'supplier_quotation_number' => 'nullable|string|max:255',
            'discount_type' => 'required|string|in:without_discount,item_wise,order_wise',
            'tax_type' => 'required|string|in:without_tax,item_wise_tax,order_wise_tax',
            'gst_type' => 'required|string|in:cgst_sgst,igst',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'cgst_amount' => 'required|numeric|min:0',
            'sgst_amount' => 'required|numeric|min:0',
            'igst_amount' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
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

        return DB::transaction(function () use ($validated, $order) {
            $order->update([
                'purchase_requisition_id' => $validated['purchase_requisition_id'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'location' => $validated['location'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'supplier_quotation_number' => $validated['supplier_quotation_number'] ?? null,
                'date' => $validated['date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'discount_type' => $validated['discount_type'],
                'tax_type' => $validated['tax_type'],
                'gst_type' => $validated['gst_type'],
                'subtotal' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'],
                'cgst_amount' => $validated['cgst_amount'],
                'sgst_amount' => $validated['sgst_amount'],
                'igst_amount' => $validated['igst_amount'],
                'tax_amount' => $validated['tax_amount'],
                'grand_total' => $validated['grand_total'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Keep a copy of old allocations to re-allocate if items are updated
            $oldAllocationsByProduct = [];
            foreach ($order->items as $oldItem) {
                if (!empty($oldItem->requisition_item_allocations)) {
                    $oldAllocationsByProduct[$oldItem->product_id] = $oldItem->requisition_item_allocations;
                }
            }

            // Re-create items
            $order->items()->delete();

            foreach ($validated['items'] as $item) {
                $productId = $item['product_id'];
                $totalQty = (float)$item['quantity'];

                // Reconstruct allocations based on previous ones, or check header link
                $prItemIds = [];
                if (isset($oldAllocationsByProduct[$productId])) {
                    $prItemIds = collect($oldAllocationsByProduct[$productId])->pluck('pr_item_id')->filter()->toArray();
                }

                $prItems = collect();
                if (!empty($prItemIds)) {
                    $prItems = PurchaseRequisitionItem::whereIn('id', $prItemIds)->get();
                } elseif (!empty($validated['purchase_requisition_id'])) {
                    $prItems = PurchaseRequisitionItem::where('purchase_requisition_id', $validated['purchase_requisition_id'])
                        ->where('product_id', $productId)
                        ->get();
                }

                $allocations = [];
                if ($prItems->isNotEmpty()) {
                    $remainingQty = $totalQty;

                    foreach ($prItems as $prItem) {
                        if ($remainingQty <= 0) break;

                        $alreadyOrdered = (float)$prItem->fresh()->ordered_qty;
                        $pending = max(0.0, (float)$prItem->quantity - $alreadyOrdered);

                        if ($pending > 0.0001) {
                            $qtyToAlloc = min($remainingQty, $pending);
                            $remainingQty -= $qtyToAlloc;

                            $allocations[] = [
                                'pr_item_id' => $prItem->id,
                                'quantity' => $qtyToAlloc,
                            ];
                        }
                    }

                    if ($remainingQty > 0.0001) {
                        $allocations[] = [
                            'pr_item_id' => null,
                            'quantity' => $remainingQty,
                        ];
                    }
                }

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $productId,
                    'requisition_item_allocations' => empty($allocations) ? null : $allocations,
                    'quantity' => $totalQty,
                    'rate' => $item['rate'],
                    'amount' => $item['amount'],
                    'discount_percent' => $item['discount_percent'] ?? 0.00,
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'tax_percent' => $item['tax_percent'] ?? 0.00,
                    'cgst_percent' => $item['cgst_percent'] ?? 0.00,
                    'sgst_percent' => $item['sgst_percent'] ?? 0.00,
                    'igst_percent' => $item['igst_percent'] ?? 0.00,
                    'cgst_amount' => $item['cgst_amount'] ?? 0.00,
                    'sgst_amount' => $item['sgst_amount'] ?? 0.00,
                    'igst_amount' => $item['igst_amount'] ?? 0.00,
                    'tax_amount' => $item['tax_amount'] ?? 0.00,
                    'total_amount' => $item['total_amount'] ?? $item['amount'],
                ]);
            }

            return redirect()->route('purchase.orders.show', $order->id)
                ->with('success', "Purchase Order updated successfully.");
        });
    }

    public function destroy(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status !== 'Draft') {
            return redirect()->route('purchase.orders.show', $id)
                ->with('error', 'Only Draft Purchase Orders can be deleted.');
        }

        $order->delete();

        return redirect()->route('purchase.orders.index')
            ->with('success', 'Purchase Order deleted successfully.');
    }

    public function approve(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status === 'Draft') {
            DB::transaction(function () use ($order) {
                // Increment ordered_qty on allocated PR items
                foreach ($order->items as $item) {
                    if (!empty($item->requisition_item_allocations)) {
                        foreach ($item->requisition_item_allocations as $alloc) {
                            if (!empty($alloc['pr_item_id'])) {
                                DB::table('purchase_requisition_items')
                                    ->where('id', $alloc['pr_item_id'])
                                    ->increment('ordered_qty', (float)$alloc['quantity']);
                            }
                        }
                    }
                }
                $order->update(['status' => 'Approved']);
            });

            return redirect()->route('purchase.orders.show', $id)
                ->with('success', 'Purchase Order approved successfully.');
        }

        return redirect()->route('purchase.orders.show', $id)
            ->with('error', 'Only Draft Purchase Orders can be approved.');
    }

    public function reject(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($id);

        if ($order->status === 'Draft') {
            $order->update(['status' => 'Cancelled']);

            return redirect()->route('purchase.orders.show', $id)
                ->with('success', 'Purchase Order rejected successfully.');
        }

        return redirect()->route('purchase.orders.show', $id)
            ->with('error', 'Only Draft Purchase Orders can be rejected.');
    }

    public function getRequisitionItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $requisitionId = (int) $request->query('requisition_id');
        $excludePoId = $request->query('exclude_po_id');

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['items.product', 'items.warehouse'])
            ->find($requisitionId);

        if (!$requisition) {
            return response()->json(['success' => false, 'error' => 'Requisition not found.'], 404);
        }

        $items = [];
        $existingPoQuery = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('purchase_requisition_id', $requisitionId)
            ->where('status', '!=', 'Cancelled');

        if ($excludePoId) {
            $existingPoQuery->where('id', '!=', (int)$excludePoId);
        }

        $existingPos = $existingPoQuery->with('items')->get();

        foreach ($requisition->items as $item) {
            $alreadyOrderedQty = 0.0;
            foreach ($existingPos as $po) {
                $alreadyOrderedQty += (float) $po->items->where('product_id', $item->product_id)->sum('quantity');
            }
            $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

            if ($pendingQty > 0.0001) {
                $items[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name . ($item->product->sku ? ' (' . $item->product->sku . ')' : ''),
                    'quantity' => $pendingQty,
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_name' => $item->warehouse->name ?? '—',
                    'rate' => (float)($item->product->unit_cost ?? $item->estimated_cost ?? 0.00),
                    'estimated_cost' => (float)$item->estimated_cost,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function downloadPdf(int $id)
    {
        $tenantId = require_tenant_id();
        $order = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['vendor', 'requisition', 'creator', 'items.product', 'warehouse'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('modules.purchase.orders.pdf', [
            'order' => $order,
        ]);

        return $pdf->download("PurchaseOrder_{$order->purchase_order_number}.pdf");
    }
}
