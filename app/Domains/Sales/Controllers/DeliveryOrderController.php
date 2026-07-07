<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\DeliveryOrder;
use App\Domains\Sales\Models\DeliveryOrderItem;
use App\Domains\Sales\Services\DeliveryOrderService;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Batch;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryOrderController extends Controller
{
    public function __construct(
        private readonly DeliveryOrderService $deliveryService,
    ) {
    }

    public function index(): View
    {
        $deliveries = DeliveryOrder::with('salesOrder.customer')->latest()->get();

        return view('modules.sales.deliveries.index', [
            'deliveries' => $deliveries,
        ]);
    }

    public function create(Request $request): View
    {
        $salesOrderId = $request->input('sales_order_id');
        $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->findOrFail($salesOrderId);

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

        return view('modules.sales.deliveries.create', [
            'salesOrder' => $salesOrder,
            'shippedQuantities' => $shippedQuantities,
            'warehouses' => $warehouses,
            'nextDeliveryNumber' => $this->deliveryService->getNextDeliveryNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        // Fetch serial numbers and batches available for selection if in Draft status
        $itemAllocations = [];
        if ($delivery->status === 'Draft') {
            foreach ($delivery->items as $item) {
                if (!$item->product_id) continue;

                $allocatedSerials = [];
                $allocatedBatches = [];

                if ($item->product->track_serial_number) {
                    $allocatedSerials = SerialNumber::query()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->whereIn('status', ['Available', 'Reserved'])
                        ->orderBy('serial_number')
                        ->get();
                }

                if ($item->product->track_batch) {
                    $allocatedBatches = Batch::query()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->where('available_qty', '>', 0)
                        ->orderBy('expiry_date')
                        ->get();
                }

                $itemAllocations[$item->id] = [
                    'serials' => $allocatedSerials,
                    'batches' => $allocatedBatches,
                ];
            }
        }

        return view('modules.sales.deliveries.show', [
            'delivery' => $delivery,
            'itemAllocations' => $itemAllocations,
        ]);
    }

    public function ship(int $id, Request $request): RedirectResponse
    {
        $delivery = DeliveryOrder::findOrFail($id);

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

        try {
            $this->deliveryService->cancel($delivery);
            return back()->with('success', 'Delivery Order cancelled successfully.');
        } catch (\Exception $e) {
            return back()->withErrors([$e->getMessage()]);
        }
    }
}
