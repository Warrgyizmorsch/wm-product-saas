<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\MaterialRequirementService;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialRequirementController extends Controller
{
    public function __construct(
        private readonly MaterialRequirementService $deliveryService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MaterialRequirement::class);

        $deliveries = MaterialRequirement::with('salesOrder.customer')->latest()->get();

        return view('modules.sales.material-requirements.index', [
            'deliveries' => $deliveries,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MaterialRequirement::class);

        $salesOrderId = $request->input('sales_order_id');
        $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->findOrFail($salesOrderId);

        // Filter items to show only 'buy' items for Delivery Order
        $salesOrder->setRelation('items', $salesOrder->items->filter(function ($item) {
            return ! $item->product || $item->product->supplier_method === 'buy' || is_null($item->product->supplier_method);
        }));

        if (! in_array($salesOrder->status, ['Confirmed', 'Partially Shipped'])) {
            abort(400, 'Material Requirements can only be created for Confirmed or Partially Shipped Sales Orders.');
        }

        // Calculate already shipped quantities for each sales order line
        $shippedQuantities = [];
        foreach ($salesOrder->items as $item) {
            $shippedQuantities[$item->id] = MaterialRequirementItem::query()
                ->whereHas('materialRequirement', function ($q) {
                    $q->where('status', 'Shipped');
                })
                ->where('sales_order_item_id', $item->id)
                ->sum('quantity');
        }

        $warehouses = Warehouse::query()->where('status', 'active')->orderBy('name')->get();

        // Calculate available stock map for these products across all warehouses
        $productIds = $salesOrder->items->pluck('product_id')->filter()->unique()->toArray();
        $stocks = ProductWarehouseStock::query()
            ->whereIn('product_id', $productIds)
            ->get();

        $stockMap = [];
        foreach ($stocks as $stock) {
            $stockMap[$stock->product_id][$stock->warehouse_id] = (float) $stock->available_qty;
        }

        return view('modules.sales.material-requirements.create', [
            'salesOrder' => $salesOrder,
            'shippedQuantities' => $shippedQuantities,
            'warehouses' => $warehouses,
            'stockMap' => $stockMap,
            'nextDeliveryNumber' => $this->deliveryService->getNextRequirementNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MaterialRequirement::class);

        $validated = $request->validate([
            'sales_order_id' => ['required', 'exists:sales_orders,id'],
            'requirement_number' => ['required', 'string', 'max:255'],
            'requirement_date' => ['required', 'date'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ]);

        try {
            // Re-map keys for Service compatibility
            $mappedData = $validated;
            $mappedData['sales_order_id'] = $validated['sales_order_id'];
            $mappedData['requirement_number'] = $validated['requirement_number'];
            $mappedData['requirement_date'] = $validated['requirement_date'];
            
            $delivery = $this->deliveryService->create($mappedData, $request->input('items', []));

            return redirect()
                ->route('sales.material-requirements.show', $delivery->id)
                ->with('success', 'Material Requirement successfully created!');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()])->withInput();
        }
    }

    public function show(int $id): View
    {
        $delivery = MaterialRequirement::with('salesOrder.customer', 'items.product', 'items.warehouse', 'items.salesOrderItem')->findOrFail($id);

        $this->authorize('view', $delivery);

        $warehouses = Warehouse::query()->where('status', 'active')->orderBy('name')->get();

        $defaultWarehouseId = Warehouse::where('tenant_id', $delivery->tenant_id)
            ->orderBy('is_default', 'desc')
            ->first()?->id ?? 1;

        foreach ($delivery->items as $item) {
            $warehouseId = $item->warehouse_id ?: $defaultWarehouseId;
            $item->available_qty = $warehouseId ? StockService::getAvailableStock($item->product_id, $warehouseId) : 0;
        }

        $itemAllocations = [];

        return view('modules.sales.material-requirements.show', [
            'delivery' => $delivery,
            'warehouses' => $warehouses,
            'defaultWarehouseId' => $defaultWarehouseId,
            'itemAllocations' => $itemAllocations,
        ]);
    }

    public function updateWarehouse(Request $request, int $itemId)
    {
        $item = MaterialRequirementItem::findOrFail($itemId);
        $warehouseId = (int) $request->input('warehouse_id');

        $item->update([
            'warehouse_id' => $warehouseId,
        ]);

        $availableStock = StockService::getAvailableStock($item->product_id, $warehouseId);

        return response()->json([
            'success' => true,
            'available_qty' => $availableStock,
        ]);
    }

    public function reserveQty(Request $request, int $itemId)
    {
        $item = MaterialRequirementItem::with('materialRequirement')->findOrFail($itemId);
        $qtyToReserve = (float) $request->input('quantity_reserve');

        if ($qtyToReserve <= 0) {
            return back()->with('error', 'Reserve quantity must be greater than 0.');
        }

        $warehouseId = $item->warehouse_id;
        $productId = $item->product_id;
        $tenantId = $item->materialRequirement->tenant_id;

        $availableStock = StockService::getAvailableStock($productId, $warehouseId);

        if ($qtyToReserve > $availableStock) {
            return back()->with('error', "Cannot reserve {$qtyToReserve}. Only {$availableStock} is available in this warehouse.");
        }

        // Adjust reservation in DB via StockService
        StockService::reserveStock(
            $tenantId,
            $productId,
            $warehouseId,
            $qtyToReserve,
            'DeliveryOrder', // context name inside StockService
            $item->material_requirement_id,
            $item->id
        );

        $item->increment('quantity_reserved', $qtyToReserve);

        if ($item->quantity_reserved >= $item->quantity_ordered) {
            $item->update(['status' => 'Reserved']);
        } else {
            $item->update(['status' => 'Partially Reserved']);
        }

        $this->updateOverallDeliveryStatus($item->materialRequirement);

        return back()->with('success', "Successfully reserved {$qtyToReserve} unit(s).");
    }

    public function mockIndent(Request $request, int $itemId)
    {
        $tenantId = require_tenant_id();
        $item = MaterialRequirementItem::with('materialRequirement')->findOrFail($itemId);
        $qtyToRequest = (float) $request->input('quantity_request');
        $warehouseId = (int) $request->input('warehouse_id', $item->warehouse_id);
        $notes = $request->input('notes');

        if ($qtyToRequest <= 0) {
            return back()->with('error', 'Quantity to request must be greater than 0.');
        }

        DB::transaction(function () use ($item, $qtyToRequest, $warehouseId, $notes, $tenantId) {
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
                'requisition_date' => now()->toDateString(),
                'status' => 'Draft',
                'source_type' => 'material_requirement',
                'source_id' => $item->material_requirement_id,
                'notes' => $notes ?: 'Generated from Material Requirement #' . $item->materialRequirement->requirement_number,
                'requested_by' => auth()->id() ?: 1,
            ]);

            PurchaseRequisitionItem::create([
                'purchase_requisition_id' => $pr->id,
                'product_id' => $item->product_id,
                'quantity' => $qtyToRequest,
                'warehouse_id' => $warehouseId,
                'estimated_cost' => $item->product->unit_cost ?? 0.00,
            ]);

            $item->update([
                'status' => 'Waiting Purchase',
            ]);

            $this->updateOverallDeliveryStatus($item->materialRequirement);
        });

        return back()->with('success', "Purchase Requisition successfully generated for {$qtyToRequest} unit(s).");
    }

    public function mockMo(Request $request, int $itemId)
    {
        $tenantId = require_tenant_id();
        $item = MaterialRequirementItem::with('materialRequirement')
            ->whereHas('materialRequirement', fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($itemId);

        $orderedQty = (float) ($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
        $reservedQty = (float) $item->quantity_reserved;
        $shortageQty = max(0, $orderedQty - $reservedQty);

        $qtyToMfg = (float) $request->input('quantity_mfg', $shortageQty > 0 ? $shortageQty : $orderedQty);

        if ($qtyToMfg <= 0) {
            return back()->withErrors(['quantity_mfg' => 'Quantity to manufacture must be greater than 0.']);
        }

        try {
            DB::transaction(function () use ($item, $qtyToMfg, $request, $tenantId) {
                $existingRequest = ProductionOrderRequest::where('tenant_id', $tenantId)
                    ->where('material_requirement_item_id', $item->id)
                    ->whereNotIn('status', ['rejected', 'completed', 'cancelled'])
                    ->lockForUpdate()
                    ->first();

                if ($existingRequest) {
                    throw new \InvalidArgumentException('A production request already exists for this material requirement line.');
                }

                // Create a Production Order Request in draft status
                ProductionOrderRequest::create([
                    'tenant_id' => $tenantId,
                    'material_requirement_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity_requested' => $qtyToMfg,
                    'status' => 'draft',
                    'notes' => $request->input('notes') ?? "Requested from MR {$item->materialRequirement->requirement_number}",
                    'created_by' => auth()->id(),
                ]);

                // Update material requirement item status
                $item->update([
                    'status' => 'Waiting Production',
                ]);

                $this->updateOverallDeliveryStatus($item->materialRequirement);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity_mfg' => $e->getMessage()]);
        }

        return back()->with('success', 'Manufacturing Request submitted successfully. Line status set to Waiting Production.');
    }

    public function startPicking(int $id)
    {
        $delivery = MaterialRequirement::findOrFail($id);

        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Picked']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Picked']);
            }
        });

        return back()->with('success', 'Picking process started. Status set to Picked.');
    }

    public function pack(int $id)
    {
        $delivery = MaterialRequirement::findOrFail($id);

        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Packed']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Packed']);
            }
        });

        return back()->with('success', 'Package packed successfully. Status set to Packed.');
    }

    public function dispatch(int $id, Request $request)
    {
        $delivery = MaterialRequirement::with('items.product', 'salesOrder')->findOrFail($id);

        $request->validate([
            'carrier' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($delivery, $request) {
            $delivery->update([
                'status' => 'Dispatched',
                'carrier' => $request->input('carrier'),
                'tracking_number' => $request->input('tracking_number'),
                'notes' => $request->input('notes'),
            ]);

            foreach ($delivery->items as $doItem) {
                $doItem->update(['status' => 'Dispatched']);

                if (! $doItem->product_id || $doItem->product->type === 'Service') {
                    continue;
                }

                $tenantId = $delivery->tenant_id;
                $productId = $doItem->product_id;
                $warehouseId = $doItem->warehouse_id;
                $qty = (float) $doItem->quantity_ordered;

                // Release stock reservation
                StockService::releaseStock(
                    $tenantId,
                    $productId,
                    $warehouseId,
                    $qty,
                    'DeliveryOrder',
                    $delivery->id,
                    $doItem->id
                );

                // Record actual stock outflow
                StockService::recordOutflow(
                    $tenantId,
                    $productId,
                    $warehouseId,
                    $qty,
                    'DeliveryOrder',
                    $delivery->id,
                    []
                );
            }

            $delivery->salesOrder->update(['status' => 'Shipped']);
        });

        return back()->with('success', 'Order dispatched successfully. Stock ledger entries recorded.');
    }

    public function deliver(int $id)
    {
        $delivery = MaterialRequirement::with('items', 'salesOrder')->findOrFail($id);

        DB::transaction(function () use ($delivery) {
            $delivery->update(['status' => 'Delivered']);
            foreach ($delivery->items as $item) {
                $item->update(['status' => 'Delivered']);
            }
            $delivery->salesOrder->update(['status' => 'Delivered']);
        });

        return back()->with('success', 'Order delivered successfully! Ready for invoicing.');
    }

    public function ship(int $id, Request $request): RedirectResponse
    {
        $delivery = MaterialRequirement::findOrFail($id);

        $this->authorize('ship', $delivery);

        $allocations = $request->input('allocations', []);

        // Validate serial number allocations
        foreach ($delivery->items as $item) {
            if (! $item->product_id || $item->product->type === 'Service') {
                continue;
            }

            if ($item->product->track_serial_number) {
                $serials = $allocations[$item->id]['serials'] ?? [];
                $serials = array_filter(array_map('trim', $serials));

                if (count($serials) != (int) $item->quantity) {
                    return back()->withErrors(['Please select exactly '.(int) $item->quantity.' serial number(s) for item: '.$item->product->name]);
                }

                // Check that selected serials belong to this product, warehouse and are available/reserved
                $validCount = SerialNumber::query()
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->whereIn('status', ['Available', 'Reserved'])
                    ->whereIn('serial_number', $serials)
                    ->count();

                if ($validCount != count($serials)) {
                    return back()->withErrors(["One or more selected serial numbers for product '".$item->product->name."' are invalid or already sold."]);
                }
            }
        }

        try {
            $this->deliveryService->ship($delivery, $allocations);

            return redirect()
                ->route('sales.material-requirements.show', $delivery->id)
                ->with('success', 'Material Requirement shipped successfully! Inventory updated.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }

    public function cancel(int $id): RedirectResponse
    {
        $delivery = MaterialRequirement::findOrFail($id);

        $this->authorize('cancel', $delivery);

        try {
            $this->deliveryService->cancel($delivery);

            return back()->with('success', 'Material Requirement cancelled successfully.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Create a Dispatch Order from a Material Requirement.
     */
    public function storeDispatchOrder(int $deliveryId, Request $request): RedirectResponse
    {
        $delivery = MaterialRequirement::with('items.product', 'items.warehouse', 'salesOrder')->findOrFail($deliveryId);

        $request->validate([
            'carrier' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'vehicle_number' => 'nullable|string|max:100',
            'driver_name' => 'nullable|string|max:150',
            'driver_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $dispatchOrder = DB::transaction(function () use ($delivery, $request) {
            // Generate next dispatch number
            $year = now()->format('Y');
            $prefix = "DSP-{$year}-";

            $latest = DispatchOrder::where('tenant_id', $delivery->tenant_id)
                ->where('dispatch_number', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->first();

            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->dispatch_number);
                $nextNum = intval($lastNumStr) + 1;
            }
            $dispatchNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $dispatchOrder = DispatchOrder::create([
                'tenant_id' => $delivery->tenant_id,
                'material_requirement_id' => $delivery->id,
                'sales_order_id' => $delivery->sales_order_id,
                'dispatch_number' => $dispatchNumber,
                'dispatch_date' => now()->toDateString(),
                'carrier' => $request->input('carrier'),
                'tracking_number' => $request->input('tracking_number'),
                'vehicle_number' => $request->input('vehicle_number'),
                'driver_name' => $request->input('driver_name'),
                'driver_phone' => $request->input('driver_phone'),
                'status' => 'Pending',
                'notes' => $request->input('notes'),
            ]);

            foreach ($delivery->items as $doItem) {
                $orderedQty = (float) ($doItem->quantity_ordered > 0 ? $doItem->quantity_ordered : $doItem->quantity);
                $reservedQty = (float) $doItem->quantity_reserved;

                // Dispatch whatever qty we have (reserved first, else full ordered qty)
                $dispatchQty = $reservedQty > 0 ? $reservedQty : $orderedQty;

                if ($dispatchQty <= 0) {
                    continue;
                }

                DispatchOrderItem::create([
                    'dispatch_order_id' => $dispatchOrder->id,
                    'material_requirement_item_id' => $doItem->id,
                    'product_id' => $doItem->product_id,
                    'warehouse_id' => $doItem->warehouse_id,
                    'quantity_ordered' => $orderedQty,
                    'quantity_dispatched' => $dispatchQty,
                ]);
            }

            return $dispatchOrder;
        });

        return redirect()
            ->route('sales.dispatches.show', $dispatchOrder->id)
            ->with('success', "Dispatch Order {$dispatchOrder->dispatch_number} created successfully!");
    }

    private function updateOverallDeliveryStatus(MaterialRequirement $delivery): void
    {
        $items = $delivery->items()->get();
        if ($items->isEmpty()) {
            return;
        }

        $allDelivered = true;
        $allDispatched = true;
        $allPacked = true;
        $allPicked = true;
        $allReady = true;
        $allPending = true;
        $anyReadyOrReserved = false;

        foreach ($items as $item) {
            $status = $item->status;

            if ($status !== 'Delivered') {
                $allDelivered = false;
            }
            if ($status !== 'Dispatched') {
                $allDispatched = false;
            }
            if ($status !== 'Packed') {
                $allPacked = false;
            }
            if ($status !== 'Picked') {
                $allPicked = false;
            }
            if ($status !== 'Ready' && $status !== 'Reserved') {
                $allReady = false;
            }
            if ($status !== 'Pending') {
                $allPending = false;
            }
            if ($status === 'Reserved' || $status === 'Ready') {
                $anyReadyOrReserved = true;
            }
        }

        if ($allDelivered) {
            $delivery->update(['status' => 'Delivered']);
        } elseif ($allDispatched) {
            $delivery->update(['status' => 'Dispatched']);
        } elseif ($allPacked) {
            $delivery->update(['status' => 'Packed']);
        } elseif ($allPicked) {
            $delivery->update(['status' => 'Picked']);
        } elseif ($allReady) {
            $delivery->update(['status' => 'Ready']);
        } elseif ($allPending) {
            $delivery->update(['status' => 'Pending']);
        } elseif ($anyReadyOrReserved) {
            $delivery->update(['status' => 'Partially Ready']);
        } else {
            $delivery->update(['status' => 'Processing']);
        }
    }
}
