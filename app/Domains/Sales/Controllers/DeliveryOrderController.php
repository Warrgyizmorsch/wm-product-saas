<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\DeliveryOrder;
use App\Domains\Sales\Models\DeliveryOrderItem;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\Sales\Services\DeliveryOrderService;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Batch;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function __construct(
        private readonly DeliveryOrderService $deliveryService,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', DeliveryOrder::class);

        $deliveries = DeliveryOrder::with('salesOrder.customer')->latest()->get();

        return view('modules.sales.deliveries.index', [
            'deliveries' => $deliveries,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DeliveryOrder::class);

        $salesOrderId = $request->input('sales_order_id');
        $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->findOrFail($salesOrderId);

        // Filter items to show only 'buy' items for Delivery Order
        $salesOrder->setRelation('items', $salesOrder->items->filter(function($item) {
            return !$item->product || $item->product->supplier_method === 'buy' || is_null($item->product->supplier_method);
        }));

        if (!in_array($salesOrder->status, ['Confirmed', 'Partially Shipped'])) {
            abort(400, 'Deliveries can only be created for Confirmed or Partially Shipped Sales Orders.');
        }

        // Calculate already shipped quantities for each sales order line
        $shippedQuantities = [];
        foreach ($salesOrder->items as $item) {
            $shippedQuantities[$item->id] = DeliveryOrderItem::query()
                ->whereHas('deliveryOrder', function($q) {
                    $q->where('status', 'Shipped');
                })
                ->where('sales_order_item_id', $item->id)
                ->sum('quantity');
        }

        $warehouses = Warehouse::query()->where('status', 'active')->orderBy('name')->get();

        // Calculate available stock map for these products across all warehouses
        $productIds = $salesOrder->items->pluck('product_id')->filter()->unique()->toArray();
        $stocks = \App\Domains\Inventory\Models\ProductWarehouseStock::query()
            ->whereIn('product_id', $productIds)
            ->get();

        $stockMap = [];
        foreach ($stocks as $stock) {
            $stockMap[$stock->product_id][$stock->warehouse_id] = (float)$stock->available_qty;
        }

        return view('modules.sales.deliveries.create', [
            'salesOrder' => $salesOrder,
            'shippedQuantities' => $shippedQuantities,
            'warehouses' => $warehouses,
            'stockMap' => $stockMap,
            'nextDeliveryNumber' => $this->deliveryService->getNextDeliveryNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DeliveryOrder::class);

        $validated = $request->validate([
            'sales_order_id'   => ['required', 'exists:sales_orders,id'],
            'delivery_number'  => ['required', 'string', 'max:255'],
            'delivery_date'    => ['required', 'date'],
            'carrier'          => ['nullable', 'string', 'max:255'],
            'tracking_number'  => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
            'items'            => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ]);

        try {
            $delivery = $this->deliveryService->create($validated, $request->input('items', []));
            return redirect()
                ->route('sales.deliveries.show', $delivery->id)
                ->with('success', 'Delivery Order successfully created!');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()])->withInput();
        }
    }

    public function show(int $id): View
    {
        $delivery = DeliveryOrder::with('salesOrder.customer', 'items.product', 'items.warehouse', 'items.salesOrderItem')->findOrFail($id);

        $this->authorize('view', $delivery);

        $warehouses = Warehouse::query()->where('status', 'active')->orderBy('name')->get();

        $defaultWarehouseId = Warehouse::where('tenant_id', $delivery->tenant_id)
            ->orderBy('is_default', 'desc')
            ->first()?->id ?? 1;

        foreach ($delivery->items as $item) {
            $warehouseId = $item->warehouse_id ?: $defaultWarehouseId;
            $item->available_qty = $warehouseId ? \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $warehouseId) : 0;
        }

        $itemAllocations = [];

        return view('modules.sales.deliveries.show', [
            'delivery' => $delivery,
            'warehouses' => $warehouses,
            'defaultWarehouseId' => $defaultWarehouseId,
            'itemAllocations' => $itemAllocations,
        ]);
    }

    public function updateWarehouse(Request $request, int $itemId)
    {
        $item = DeliveryOrderItem::findOrFail($itemId);
        $warehouseId = (int)$request->input('warehouse_id');

        $item->update([
            'warehouse_id' => $warehouseId,
        ]);

        $availableStock = \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $warehouseId);

        return response()->json([
            'success' => true,
            'available_qty' => $availableStock,
        ]);
    }

    public function reserveQty(Request $request, int $itemId)
    {
        $item = DeliveryOrderItem::with('deliveryOrder')->findOrFail($itemId);
        $qtyToReserve = (float)$request->input('quantity_reserve');

        if ($qtyToReserve <= 0) {
            return back()->with('error', 'Reserve quantity must be greater than 0.');
        }

        $warehouseId = $item->warehouse_id;
        $productId = $item->product_id;
        $tenantId = $item->deliveryOrder->tenant_id;

        $availableStock = \App\Domains\Inventory\Services\StockService::getAvailableStock($productId, $warehouseId);

        if ($qtyToReserve > $availableStock) {
            return back()->with('error', "Cannot reserve {$qtyToReserve}. Only {$availableStock} is available in this warehouse.");
        }

        // Adjust reservation in DB via StockService
        \App\Domains\Inventory\Services\StockService::reserveStock(
            $tenantId,
            $productId,
            $warehouseId,
            $qtyToReserve,
            'DeliveryOrder',
            $item->delivery_order_id,
            $item->id
        );

        $item->increment('quantity_reserved', $qtyToReserve);

        if ($item->quantity_reserved >= $item->quantity_ordered) {
            $item->update(['status' => 'Reserved']);
        } else {
            $item->update(['status' => 'Partially Reserved']);
        }

        $this->updateOverallDeliveryStatus($item->deliveryOrder);

        return back()->with('success', "Successfully reserved {$qtyToReserve} unit(s).");
    }

    public function mockIndent(Request $request, int $itemId)
    {
        $item = DeliveryOrderItem::with('deliveryOrder')->findOrFail($itemId);
        $qtyToRequest = (float)$request->input('quantity_request');

        $item->update([
            'status' => 'Waiting Purchase',
        ]);

        $this->updateOverallDeliveryStatus($item->deliveryOrder);

        return back()->with('success', "Purchase request (Simulated) generated for {$qtyToRequest} unit(s). Line status set to Waiting Purchase.");
    }

    public function mockMo(Request $request, int $itemId)
    {
        $item = DeliveryOrderItem::with('deliveryOrder')->findOrFail($itemId);

        $orderedQty  = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
        $reservedQty = (float)$item->quantity_reserved;
        $shortageQty = max(0, $orderedQty - $reservedQty);

        $qtyToMfg = (float)$request->input('quantity_mfg', $shortageQty > 0 ? $shortageQty : $orderedQty);

        if ($qtyToMfg <= 0) {
            return back()->withErrors(['quantity_mfg' => 'Quantity to manufacture must be greater than 0.']);
        }

        DB::transaction(function () use ($item, $qtyToMfg, $request) {
            // Create a Production Order Request in draft status
            \App\Domains\Production\Models\ProductionOrderRequest::create([
                'tenant_id'              => $item->deliveryOrder->tenant_id,
                'delivery_order_item_id' => $item->id,
                'product_id'             => $item->product_id,
                'quantity_requested'     => $qtyToMfg,
                'status'                 => 'draft',
                'notes'                  => $request->input('notes') ?? "Requested from DO {$item->deliveryOrder->delivery_number}",
                'created_by'             => auth()->id(),
            ]);

            // Update delivery order item status
            $item->update([
                'status' => 'Waiting Production',
            ]);

            $this->updateOverallDeliveryStatus($item->deliveryOrder);
        });

        return back()->with('success', "Manufacturing Request submitted successfully. Line status set to Waiting Production.");
    }

    public function startPicking(int $id)
    {
        $delivery = DeliveryOrder::findOrFail($id);
        
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
        $delivery = DeliveryOrder::findOrFail($id);
        
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
        $delivery = DeliveryOrder::with('items.product', 'salesOrder')->findOrFail($id);

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

                if (!$doItem->product_id || $doItem->product->type === 'Service') continue;

                $tenantId = $delivery->tenant_id;
                $productId = $doItem->product_id;
                $warehouseId = $doItem->warehouse_id;
                $qty = (float)$doItem->quantity_ordered;

                // Release stock reservation
                \App\Domains\Inventory\Services\StockService::releaseStock(
                    $tenantId,
                    $productId,
                    $warehouseId,
                    $qty,
                    'DeliveryOrder',
                    $delivery->id,
                    $doItem->id
                );

                // Record actual stock outflow
                \App\Domains\Inventory\Services\StockService::recordOutflow(
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
        $delivery = DeliveryOrder::with('items', 'salesOrder')->findOrFail($id);

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
        $delivery = DeliveryOrder::findOrFail($id);

        $this->authorize('ship', $delivery);

        $allocations = $request->input('allocations', []);

        // Validate serial number allocations
        foreach ($delivery->items as $item) {
            if (!$item->product_id || $item->product->type === 'Service') continue;

            if ($item->product->track_serial_number) {
                $serials = $allocations[$item->id]['serials'] ?? [];
                $serials = array_filter(array_map('trim', $serials));

                if (count($serials) != (int)$item->quantity) {
                    return back()->withErrors(["Please select exactly " . (int)$item->quantity . " serial number(s) for item: " . $item->product->name]);
                }

                // Check that selected serials belong to this product, warehouse and are available/reserved
                $validCount = SerialNumber::query()
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->whereIn('status', ['Available', 'Reserved'])
                    ->whereIn('serial_number', $serials)
                    ->count();

                if ($validCount != count($serials)) {
                    return back()->withErrors(["One or more selected serial numbers for product '" . $item->product->name . "' are invalid or already sold."]);
                }
            }
        }

        try {
            $this->deliveryService->ship($delivery, $allocations);
            return redirect()
                ->route('sales.deliveries.show', $delivery->id)
                ->with('success', 'Delivery shipped successfully! Inventory updated.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }

    public function cancel(int $id): RedirectResponse
    {
        $delivery = DeliveryOrder::findOrFail($id);

        $this->authorize('cancel', $delivery);

        try {
            $this->deliveryService->cancel($delivery);
            return back()->with('success', 'Delivery Order cancelled successfully.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Create a Dispatch Order from a Delivery Order.
     * Uses reserved qty if available, otherwise falls back to quantity_ordered.
     */
    public function storeDispatchOrder(int $deliveryId, Request $request): RedirectResponse
    {
        $delivery = DeliveryOrder::with('items.product', 'items.warehouse', 'salesOrder')->findOrFail($deliveryId);

        $request->validate([
            'carrier'        => 'nullable|string|max:255',
            'tracking_number'=> 'nullable|string|max:255',
            'vehicle_number' => 'nullable|string|max:100',
            'driver_name'    => 'nullable|string|max:150',
            'driver_phone'   => 'nullable|string|max:20',
            'notes'          => 'nullable|string',
        ]);

        $dispatchOrder = DB::transaction(function () use ($delivery, $request) {
            // Generate next dispatch number
            $lastNumber = DispatchOrder::where('tenant_id', $delivery->tenant_id)
                ->orderByDesc('id')
                ->value('dispatch_number');

            $nextNum = 1;
            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $nextNum = (int)$matches[1] + 1;
            }
            $dispatchNumber = 'DSP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $dispatchOrder = DispatchOrder::create([
                'tenant_id'         => $delivery->tenant_id,
                'delivery_order_id' => $delivery->id,
                'sales_order_id'    => $delivery->sales_order_id,
                'dispatch_number'   => $dispatchNumber,
                'dispatch_date'     => now()->toDateString(),
                'carrier'           => $request->input('carrier'),
                'tracking_number'   => $request->input('tracking_number'),
                'vehicle_number'    => $request->input('vehicle_number'),
                'driver_name'       => $request->input('driver_name'),
                'driver_phone'      => $request->input('driver_phone'),
                'status'            => 'Pending',
                'notes'             => $request->input('notes'),
            ]);

            foreach ($delivery->items as $doItem) {
                $orderedQty  = (float)($doItem->quantity_ordered > 0 ? $doItem->quantity_ordered : $doItem->quantity);
                $reservedQty = (float)$doItem->quantity_reserved;

                // Dispatch whatever qty we have (reserved first, else full ordered qty)
                $dispatchQty = $reservedQty > 0 ? $reservedQty : $orderedQty;

                if ($dispatchQty <= 0) continue;

                DispatchOrderItem::create([
                    'dispatch_order_id'     => $dispatchOrder->id,
                    'delivery_order_item_id'=> $doItem->id,
                    'product_id'            => $doItem->product_id,
                    'warehouse_id'          => $doItem->warehouse_id,
                    'quantity_ordered'      => $orderedQty,
                    'quantity_dispatched'   => $dispatchQty,
                ]);
            }

            return $dispatchOrder;
        });

        return redirect()
            ->route('sales.dispatches.show', $dispatchOrder->id)
            ->with('success', "Dispatch Order {$dispatchOrder->dispatch_number} created successfully!");
    }

    private function updateOverallDeliveryStatus(DeliveryOrder $delivery): void
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
