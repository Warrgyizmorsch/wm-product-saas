<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['requester', 'sourceable']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where('requisition_number', 'like', $search);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'requisition_number', 'requisition_date', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $requisitions = $query->paginate(10)->withQueryString();

        return view('modules.purchase.requisitions.index', compact('requisitions'));
    }

    public function create()
    {
        $tenantId = require_tenant_id();

        $products = Product::where('tenant_id', $tenantId)->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $productionOrders = ProductionOrder::where('tenant_id', $tenantId)->get();
        $materialRequests = ProductionRequisitionSlip::where('tenant_id', $tenantId)->get();
        $materialRequirements = MaterialRequirement::where('tenant_id', $tenantId)->get();
        $salesOrders = SalesOrder::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.requisitions.create', compact(
            'products',
            'warehouses',
            'productionOrders',
            'materialRequests',
            'materialRequirements',
            'salesOrders'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'requisition_date' => 'required|date',
            'source_type' => 'required|string|in:direct,so,mo,material_request,material_requirement,requisition_slip',
            'sales_order_id' => 'nullable|integer|exists:sales_orders,id',
            'production_order_id' => 'nullable|integer|exists:production_orders,id',
            'production_requisition_slip_id' => 'nullable|integer|exists:production_requisition_slips,id',
            'material_requirement_id' => 'nullable|integer|exists:material_requirements,id',
            'requisition_slip_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'required|numeric|min:0',
        ]);

        // Resolve polymorphic source_id
        $sourceId = null;
        if ($validated['source_type'] === 'so') {
            $sourceId = $validated['sales_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'mo') {
            $sourceId = $validated['production_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_request') {
            $sourceId = $validated['production_requisition_slip_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_requirement') {
            $sourceId = $validated['material_requirement_id'] ?? null;
        }

        return DB::transaction(function () use ($validated, $sourceId, $tenantId) {
            // Generate sequence number YYYY-000001
            $year = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date' => $validated['requisition_date'],
                'status' => 'Draft',
                'source_type' => $validated['source_type'],
                'source_id' => $sourceId,
                'requisition_slip_number' => $validated['requisition_slip_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'requested_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $pr->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'],
                ]);
            }

            return redirect()->route('purchase.requisitions.show', $pr->id)
                ->with('success', "Purchase Requisition {$requisitionNumber} created successfully.");
        });
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with([
                'requester',
                'sourceable',
                'items.product',
                'items.warehouse'
            ])
            ->findOrFail($id);

        return view('modules.purchase.requisitions.show', compact('requisition'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with('items')
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be edited.');
        }

        $products = Product::where('tenant_id', $tenantId)->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $productionOrders = ProductionOrder::where('tenant_id', $tenantId)->get();
        $materialRequests = ProductionRequisitionSlip::where('tenant_id', $tenantId)->get();
        $materialRequirements = MaterialRequirement::where('tenant_id', $tenantId)->get();
        $salesOrders = SalesOrder::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.requisitions.edit', compact(
            'requisition',
            'products',
            'warehouses',
            'productionOrders',
            'materialRequests',
            'materialRequirements',
            'salesOrders'
        ));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be updated.');
        }

        $validated = $request->validate([
            'requisition_date' => 'required|date',
            'source_type' => 'required|string|in:direct,so,mo,material_request,material_requirement,requisition_slip',
            'sales_order_id' => 'nullable|integer|exists:sales_orders,id',
            'production_order_id' => 'nullable|integer|exists:production_orders,id',
            'production_requisition_slip_id' => 'nullable|integer|exists:production_requisition_slips,id',
            'material_requirement_id' => 'nullable|integer|exists:material_requirements,id',
            'requisition_slip_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'required|numeric|min:0',
        ]);

        // Resolve polymorphic source_id
        $sourceId = null;
        if ($validated['source_type'] === 'so') {
            $sourceId = $validated['sales_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'mo') {
            $sourceId = $validated['production_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_request') {
            $sourceId = $validated['production_requisition_slip_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_requirement') {
            $sourceId = $validated['material_requirement_id'] ?? null;
        }

        return DB::transaction(function () use ($validated, $sourceId, $requisition) {
            $requisition->update([
                'requisition_date' => $validated['requisition_date'],
                'source_type' => $validated['source_type'],
                'source_id' => $sourceId,
                'requisition_slip_number' => $validated['requisition_slip_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Re-create items to avoid complex diff logic
            $requisition->items()->delete();

            foreach ($validated['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $requisition->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'],
                ]);
            }

            return redirect()->route('purchase.requisitions.show', $requisition->id)
                ->with('success', 'Purchase Requisition updated successfully.');
        });
    }

    public function destroy(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be deleted.');
        }

        DB::transaction(function () use ($requisition) {
            $requisition->items()->delete();
            $requisition->delete();
        });

        return redirect()->route('purchase.requisitions.index')
            ->with('success', 'Purchase Requisition deleted successfully.');
    }

    public function approve(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be approved.');
        }

        $requisition->update([
            'status' => 'Approved',
        ]);

        return redirect()->route('purchase.requisitions.show', $id)
            ->with('success', 'Purchase Requisition has been successfully approved.');
    }

    public function reject(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.index')
                ->with('error', 'Only Draft Purchase Requisitions can be rejected.');
        }

        $requisition->update([
            'status' => 'Cancelled',
        ]);

        return redirect()->route('purchase.requisitions.index')
            ->with('success', 'Purchase Requisition has been successfully cancelled/rejected.');
    }

    public function pendingItems(Request $request)
    {
        $tenantId = require_tenant_id();

        // 1. Fetch approved requisitions
        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->with(['items.product.vendor', 'items.warehouse'])
            ->get();

        // 2. Fetch all existing non-cancelled POs to compute ordered quantities
        $existingPos = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->whereNotNull('purchase_requisition_id')
            ->where('status', '!=', 'Cancelled')
            ->with('items')
            ->get();

        // 3. Process each requisition item to calculate pending quantity and resolve supplier
        $pendingItems = [];

        foreach ($requisitions as $pr) {
            foreach ($pr->items as $item) {
                // Sum ordered qty for this specific requisition item from the PO items table
                $alreadyOrderedQty = (float) $item->ordered_qty;

                $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

                if ($pendingQty > 0.0001) {
                    // Resolve supplier / preferred vendor
                    $product = $item->product;
                    $vendor = null;

                    if ($product->preferred_vendor_id) {
                        $vendor = $product->vendor; // Resolved via relationship
                    } else {
                        // Find last Purchase Order containing this product
                        $lastPoItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                            ->where('product_id', $product->id)
                            ->whereHas('order', function ($q) {
                                $q->where('status', 'Approved');
                            })
                            ->with('order.vendor')
                            ->orderBy('id', 'desc')
                            ->first();

                        $vendor = $lastPoItem?->order?->vendor;
                    }

                    $pendingItems[] = [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $product->name,
                        'sku' => $product->sku ?: 'No SKU',
                        'uom' => $item->uom->code ?? $product->uom->code ?? 'PCS',
                        'requisition_number' => $pr->requisition_number,
                        'requisition_id' => $pr->id,
                        'requisition_date' => $pr->requisition_date,
                        'quantity_requested' => (float) $item->quantity,
                        'quantity_ordered' => $alreadyOrderedQty,
                        'quantity_pending' => $pendingQty,
                        'estimated_cost' => (float) $item->estimated_cost,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse->name ?? '—',
                        'vendor_id' => $vendor?->id ?? null,
                        'vendor_name' => $vendor?->name ?? 'No Supplier Assigned',
                        'vendor_code' => $vendor?->code ?? '',
                    ];
                }
            }
        }

        // 4. Handle grouping parameter
        $groupBy = $request->input('group_by', 'supplier'); // 'supplier', 'pr', 'date'

        $assignedItems = [];
        $unassignedItems = [];

        foreach ($pendingItems as $pi) {
            if ($pi['vendor_id']) {
                $assignedItems[] = $pi;
            } else {
                $unassignedItems[] = $pi;
            }
        }

        // Sort assigned items by vendor name so that items of the same supplier are grouped/listed together
        usort($assignedItems, function ($a, $b) {
            return strcmp($a['vendor_name'], $b['vendor_name']);
        });

        if ($groupBy === 'pr') {
            // Sort by PR number
            usort($pendingItems, function ($a, $b) {
                return strcmp($a['requisition_number'], $b['requisition_number']);
            });
        } elseif ($groupBy === 'date') {
            // Sort by date
            usort($pendingItems, function ($a, $b) {
                $aDate = $a['requisition_date'] ? $a['requisition_date']->toDateString() : '';
                $bDate = $b['requisition_date'] ? $b['requisition_date']->toDateString() : '';
                return strcmp($aDate, $bDate);
            });
        }

        // Fetch vendors/suppliers list for dropdowns
        $vendors = \App\Domains\Inventory\Models\Vendor::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();

        return view('modules.purchase.requisitions.pending-items', compact(
            'pendingItems',
            'assignedItems',
            'unassignedItems',
            'groupBy',
            'vendors'
        ));
    }

    public function createPosFromPendingItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $selectedItemIds = $request->input('item_ids', []); // Array of requisition item IDs
        $actionType = $request->input('bulk_action', 'po'); // 'po' or 'rfq'

        if (empty($selectedItemIds)) {
            return redirect()->back()->with('error', 'Please select at least one item.');
        }

        return DB::transaction(function () use ($selectedItemIds, $actionType, $tenantId) {
            // Fetch requisition items
            $prItems = PurchaseRequisitionItem::whereIn('id', $selectedItemIds)
                ->with(['requisition', 'product'])
                ->get();

            // Calculate pending quantities for each selected item
            $existingPos = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
                ->whereNotNull('purchase_requisition_id')
                ->where('status', '!=', 'Cancelled')
                ->with('items')
                ->get();

            $itemsWithQty = [];
            foreach ($prItems as $item) {
                $alreadyOrderedQty = (float) $existingPos
                    ->where('purchase_requisition_id', $item->purchase_requisition_id)
                    ->flatMap(fn($po) => $po->items)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity');

                $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);
                if ($pendingQty > 0.0001) {
                    $itemsWithQty[] = [
                        'item' => $item,
                        'qty' => $pendingQty
                    ];
                }
            }

            if (empty($itemsWithQty)) {
                throw new \InvalidArgumentException("The selected items have already been fully ordered.");
            }

            // Group items by resolved vendor/supplier
            $vendorItems = [];
            foreach ($itemsWithQty as $entry) {
                $item = $entry['item'];
                $qty = $entry['qty'];

                // Resolve vendor: preferred_vendor or last PO vendor
                $vendorId = null;
                if ($item->product->preferred_vendor_id) {
                    $vendorId = $item->product->preferred_vendor_id;
                } else {
                    $lastPoItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->whereHas('order', function ($q) {
                            $q->where('status', 'Approved');
                        })
                        ->orderBy('id', 'desc')
                        ->first();
                    $vendorId = $lastPoItem?->order?->vendor_id;
                }

                if (!$vendorId) {
                    throw new \InvalidArgumentException("Product '{$item->product->name}' has no supplier assigned. Please assign a supplier for this item in Product Master before creating PO/RFQ.");
                }

                $vendorItems[$vendorId][] = [
                    'item' => $item,
                    'qty' => $qty
                ];
            }

            if ($actionType === 'po') {
                $poCount = 0;
                foreach ($vendorItems as $vendorId => $list) {
                    // Generate PO number
                    $year = now()->format('Y');
                    $prefix = "PO-{$year}-";
                    $latest = \App\Domains\Purchase\Models\PurchaseOrder::where('tenant_id', $tenantId)
                        ->where('purchase_order_number', 'like', "{$prefix}%")
                        ->orderBy('id', 'desc')
                        ->first();
                    $nextNum = 1;
                    if ($latest) {
                        $lastNumStr = str_replace($prefix, '', $latest->purchase_order_number);
                        $nextNum = ((int) $lastNumStr) + 1;
                    }
                    $poNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

                    $firstPrItem = $list[0]['item'];
                    $defaultWarehouse = \App\Domains\Inventory\Models\Warehouse::find($firstPrItem->warehouse_id);
                    $locationName = $defaultWarehouse?->name ?: '';

                    $po = \App\Domains\Purchase\Models\PurchaseOrder::create([
                        'tenant_id' => $tenantId,
                        'purchase_order_number' => $poNumber,
                        'purchase_requisition_id' => $firstPrItem->purchase_requisition_id,
                        'source_type' => 'requisition',
                        'vendor_id' => $vendorId,
                        'location' => $locationName,
                        'date' => now()->toDateString(),
                        'discount_type' => 'without_discount',
                        'tax_type' => 'order_wise_tax',
                        'gst_type' => 'cgst_sgst',
                        'status' => 'Draft',
                        'created_by' => auth()->id() ?: 1,
                        'subtotal' => 0,
                        'discount_amount' => 0,
                        'cgst_amount' => 0,
                        'sgst_amount' => 0,
                        'igst_amount' => 0,
                        'tax_amount' => 0,
                        'grand_total' => 0,
                        'notes' => 'Bulk generated from Pending Requisitions.',
                    ]);

                    $subtotal = 0.0;
                    foreach ($list as $entry) {
                        $item = $entry['item'];
                        $qty = $entry['qty'];
                        $rate = (float)($item->product->unit_cost ?? $item->estimated_cost ?? 0.00);
                        $amount = $qty * $rate;
                        $subtotal += $amount;

                        \App\Domains\Purchase\Models\PurchaseOrderItem::create([
                            'purchase_order_id' => $po->id,
                            'product_id' => $item->product_id,
                            'requisition_item_allocations' => [
                                [
                                    'pr_item_id' => $item->id,
                                    'quantity' => $qty,
                                ]
                            ],
                            'quantity' => $qty,
                            'rate' => $rate,
                            'amount' => $amount,
                            'total_amount' => $amount,
                        ]);
                    }

                    $po->update([
                        'subtotal' => $subtotal,
                        'grand_total' => $subtotal,
                    ]);

                    $poCount++;
                }

                return redirect()->route('purchase.orders.index')
                    ->with('success', "Successfully created {$poCount} Draft Purchase Orders for the selected pending requisition items.");

            } elseif ($actionType === 'rfq') {
                $rfqCount = 0;
                foreach ($vendorItems as $vendorId => $list) {
                    // Generate RFQ number
                    $year = now()->format('Y');
                    $prefix = "RFQ-{$year}-";
                    $latest = \App\Domains\Purchase\Models\PurchaseRfq::where('tenant_id', $tenantId)
                        ->where('rfq_number', 'like', "{$prefix}%")
                        ->orderBy('id', 'desc')
                        ->first();
                    $nextNum = 1;
                    if ($latest) {
                        $lastNumStr = str_replace($prefix, '', $latest->rfq_number);
                        $nextNum = intval($lastNumStr) + 1;
                    }
                    $rfqNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

                    $firstPrItem = $list[0]['item'];

                    // Create RFQ
                    $rfq = \App\Domains\Purchase\Models\PurchaseRfq::create([
                        'tenant_id' => $tenantId,
                        'rfq_number' => $rfqNumber,
                        'purchase_requisition_id' => $firstPrItem->purchase_requisition_id,
                        'rfq_date' => now()->toDateString(),
                        'status' => 'Draft',
                        'notes' => 'Bulk generated from Pending Requisitions.',
                        'created_by' => auth()->id() ?: 1,
                    ]);

                    // Add items
                    foreach ($list as $entry) {
                        $item = $entry['item'];
                        $qty = $entry['qty'];
                        \App\Domains\Purchase\Models\PurchaseRfqItem::create([
                            'purchase_rfq_id' => $rfq->id,
                            'product_id' => $item->product_id,
                            'quantity' => $qty,
                            'estimated_cost' => $item->estimated_cost ?? $item->product->unit_cost ?? 0.00,
                        ]);
                    }

                    // Bind Vendor
                    \App\Domains\Purchase\Models\PurchaseRfqVendor::create([
                        'tenant_id' => $tenantId,
                        'purchase_rfq_id' => $rfq->id,
                        'vendor_id' => $vendorId,
                        'token' => \Illuminate\Support\Str::random(40),
                        'status' => 'Sent',
                    ]);

                    $rfqCount++;
                }

                return redirect()->route('purchase.rfqs.index')
                    ->with('success', "Successfully created {$rfqCount} Draft RFQs for the selected pending requisition items.");
            }

            throw new \InvalidArgumentException("Invalid action type.");
        });
    }
}
