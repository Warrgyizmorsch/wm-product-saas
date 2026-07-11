<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\DeliveryOrder;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\Inventory\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DispatchOrderController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', DispatchOrder::class);

        $dispatches = DispatchOrder::with('salesOrder.customer', 'deliveryOrder')
            ->latest()
            ->get();

        // Recent delivery orders without a dispatch (for sidebar quick view)
        $pendingDOs = DeliveryOrder::with('salesOrder.customer')
            ->whereNotIn('id', DispatchOrder::pluck('delivery_order_id'))
            ->whereNotIn('status', ['Cancelled', 'Delivered'])
            ->latest()
            ->take(5)
            ->get();

        return view('modules.sales.dispatches.index', compact('dispatches', 'pendingDOs'));
    }

    public function create(): View
    {
        $this->authorize('create', DispatchOrder::class);

        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get();

        // Delivery orders without a dispatch order yet
        $pendingDOs = DeliveryOrder::with('salesOrder.customer')
            ->whereNotIn('status', ['Cancelled'])
            ->latest()
            ->get();

        return view('modules.sales.dispatches.create', compact('warehouses', 'pendingDOs'));
    }

    /**
     * AJAX: Return delivery orders available for dispatch.
     * Includes already-dispatched qty per item so the UI can cap new dispatch qty.
     */
    public function pendingDeliveryOrders(Request $request): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $dos = DeliveryOrder::with(['salesOrder.customer', 'items.product', 'items.dispatchItems'])
            ->whereNotIn('status', ['Cancelled', 'Delivered'])
            ->latest()
            ->get()
            ->map(function ($do) {
                $items = $do->items->map(function ($item) {
                    $orderedQty     = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                    $reservedQty    = (float)$item->quantity_reserved;

                    // Sum all previously dispatched qty across all dispatch orders for this line
                    $alreadyDispatched = (float)$item->dispatchItems->sum('quantity_dispatched');
                    $remainingQty      = max(0, $orderedQty - $alreadyDispatched);

                    // Suggested dispatch qty = min(reserved, remaining)
                    $suggestedQty = min($reservedQty > 0 ? $reservedQty : $orderedQty, $remainingQty);

                    return [
                        'id'                 => $item->id,
                        'product_id'         => $item->product_id,
                        'warehouse_id'       => $item->warehouse_id,
                        'product_name'       => $item->product?->name,
                        'product_sku'        => $item->product?->sku,
                        'quantity_ordered'   => (int)$orderedQty,
                        'quantity_reserved'  => (int)$reservedQty,
                        'already_dispatched' => (int)$alreadyDispatched,
                        'remaining_qty'      => (int)$remainingQty,
                        'dispatch_qty'       => (int)max(0, $suggestedQty),
                        'fully_dispatched'   => $remainingQty <= 0,
                    ];
                });

                // Only return the DO if at least one item still has remaining qty
                $hasRemaining = $items->contains(fn($i) => !$i['fully_dispatched']);

                return [
                    'id'              => $do->id,
                    'delivery_number' => $do->delivery_number,
                    'sales_order'     => $do->salesOrder->sales_order_number,
                    'customer'        => $do->salesOrder->customer?->name,
                    'status'          => $do->status,
                    'has_remaining'   => $hasRemaining,
                    'items'           => $items,
                ];
            })
            ->values();

        return response()->json($dos);
    }

    /**
     * AJAX: Get warehouse address by warehouse ID.
     */
    public function warehouseAddress(int $warehouseId): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $warehouse = Warehouse::findOrFail($warehouseId);
        return response()->json([
            'address' => $warehouse->address ?? '',
            'name'    => $warehouse->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $request->validate([
            'delivery_order_id' => 'required|exists:delivery_orders,id',
            'carrier'           => 'nullable|string|max:255',
            'tracking_number'   => 'nullable|string|max:255',
            'vehicle_number'    => 'nullable|string|max:100',
            'driver_name'       => 'nullable|string|max:150',
            'driver_phone'      => 'nullable|string|max:20',
            'dispatch_date'     => 'required|date',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.delivery_order_item_id' => 'required|integer',
            'items.*.product_id'             => 'required|integer',
            'items.*.warehouse_id'           => 'nullable|integer',
            'items.*.quantity_ordered'       => 'required|numeric|min:0',
            'items.*.quantity_dispatched'    => 'required|numeric|min:1',
        ]);

        $delivery = DeliveryOrder::findOrFail($request->delivery_order_id);

        // Validate each item's dispatch qty does not exceed remaining (ordered - already dispatched)
        foreach ($request->items as $index => $line) {
            $alreadyDispatched = DispatchOrderItem::where('delivery_order_item_id', $line['delivery_order_item_id'])
                ->sum('quantity_dispatched');
            $orderedQty    = (float)$line['quantity_ordered'];
            $remainingQty  = max(0, $orderedQty - (float)$alreadyDispatched);
            if ((float)$line['quantity_dispatched'] > $remainingQty) {
                return back()->withErrors([
                    "items.{$index}.quantity_dispatched" =>
                        "Dispatch quantity exceeds remaining quantity ({$remainingQty}) for one or more items.",
                ])->withInput();
            }
        }

        $dispatchOrder = DB::transaction(function () use ($request, $delivery) {
            // Auto-generate dispatch number
            $lastNumber = DispatchOrder::where('tenant_id', $delivery->tenant_id)
                ->orderByDesc('id')
                ->value('dispatch_number');

            $nextNum = 1;
            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $m)) {
                $nextNum = (int)$m[1] + 1;
            }
            $dispatchNumber = 'DSP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $dispatchOrder = DispatchOrder::create([
                'tenant_id'         => $delivery->tenant_id,
                'delivery_order_id' => $delivery->id,
                'sales_order_id'    => $delivery->sales_order_id,
                'dispatch_number'   => $dispatchNumber,
                'dispatch_date'     => $request->dispatch_date,
                'carrier'           => $request->carrier,
                'tracking_number'   => $request->tracking_number,
                'vehicle_number'    => $request->vehicle_number,
                'driver_name'       => $request->driver_name,
                'driver_phone'      => $request->driver_phone,
                'status'            => 'Pending',
                'notes'             => $request->notes,
            ]);

            foreach ($request->items as $line) {
                if ((float)$line['quantity_dispatched'] <= 0) continue;

                DispatchOrderItem::create([
                    'dispatch_order_id'      => $dispatchOrder->id,
                    'delivery_order_item_id' => $line['delivery_order_item_id'],
                    'product_id'             => $line['product_id'],
                    'warehouse_id'           => $line['warehouse_id'] ?? null,
                    'quantity_ordered'       => $line['quantity_ordered'],
                    'quantity_dispatched'    => $line['quantity_dispatched'],
                ]);
            }

            return $dispatchOrder;
        });

        return redirect()
            ->route('sales.dispatches.show', $dispatchOrder->id)
            ->with('success', "Dispatch Order {$dispatchOrder->dispatch_number} created successfully!");
    }

    public function show(int $id): View
    {
        $dispatch = DispatchOrder::with([
            'salesOrder.customer',
            'deliveryOrder',
            'items.product',
            'items.warehouse',
        ])->findOrFail($id);

        $this->authorize('view', $dispatch);

        return view('modules.sales.dispatches.show', compact('dispatch'));
    }
}
