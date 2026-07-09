<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Services\SalesOrderService;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrders,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', SalesOrder::class);

        return view('modules.sales.orders.index', [
            'orders' => $this->salesOrders->latest(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesOrder::class);

        $customers = Customer::query()->orderBy('name')->get();
        $products = Product::query()
            ->where('status', 'active')
            ->get();
        $salesReps = User::query()->orderBy('name')->get();
        
        // Fetch accepted/approved quotations that can be referenced
        $quotations = Quotation::query()
            ->where('is_current', true)
            ->whereIn('status', ['Approved', 'Accepted'])
            ->latest()
            ->get();

        $prefillQuotation = null;
        if ($request->has('quotation_id')) {
            $prefillQuotation = Quotation::query()->with('items.product')->find($request->input('quotation_id'));
        }

        $warehouses = Warehouse::query()->orderBy('name')->get();

        return view('modules.sales.orders.create', [
            'customers' => $customers,
            'products' => $products,
            'warehouses' => $warehouses,
            'salesReps' => $salesReps,
            'quotations' => $quotations,
            'prefillQuotation' => $prefillQuotation,
            'nextOrderNumber' => $this->salesOrders->getNextSalesOrderNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SalesOrder::class);

        $validated = $request->validate([
            'customer_id'        => ['required', 'exists:customers,id'],
            'quotation_id'       => ['nullable', 'exists:quotations,id'],
            'sales_person_id'    => ['nullable', 'exists:users,id'],
            'sales_order_number' => ['required', 'string', 'max:255'],
            'order_date'         => ['required', 'date'],
            'shipment_date'      => ['nullable', 'date', 'after_or_equal:order_date'],
            'payment_terms'      => ['nullable', 'string', 'max:255'],
            'billing_address'    => ['nullable', 'string'],
            'shipping_address'   => ['nullable', 'string'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'shipping_charges'   => ['nullable', 'numeric', 'min:0'],
            'adjustment'         => ['nullable', 'numeric'],
            'terms_conditions'   => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'items.*.product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'items.*.warehouse_id'=> ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.item_name'   => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = $this->salesOrders->create($validated, $request->input('items', []));

        return redirect()
            ->route('sales.orders.show', $order->id)
            ->with('success', 'Sales Order successfully created!');
    }

    public function show(int $id): View
    {
        $order = SalesOrder::with([
            'customer', 
            'salesPerson', 
            'quotation', 
            'items.product', 
            'items.warehouse',
            'deliveries.items', 
            'invoices.items', 
            'allocations.payment', 
            'returns.items',
            'stockAllocations.warehouse'
        ])->findOrFail($id);

        $this->authorize('view', $order);

        return view('modules.sales.orders.show', [
            'order' => $order,
        ]);
    }

    public function edit(int $id): View
    {
        $order = $this->salesOrders->find($id);

        if (!$order) {
            abort(404, 'Sales Order not found.');
        }

        $this->authorize('update', $order);

        $customers = Customer::query()->orderBy('name')->get();
        $products = Product::query()
            ->where('status', 'active')
            ->get();
        $salesReps = User::query()->orderBy('name')->get();
        
        $quotations = Quotation::query()
            ->where('is_current', true)
            ->whereIn('status', ['Approved', 'Accepted'])
            ->latest()
            ->get();

        $warehouses = Warehouse::query()->orderBy('name')->get();

        return view('modules.sales.orders.edit', [
            'order' => $order,
            'customers' => $customers,
            'products' => $products,
            'warehouses' => $warehouses,
            'salesReps' => $salesReps,
            'quotations' => $quotations,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $order = $this->salesOrders->find($id);

        if (!$order) {
            abort(404, 'Sales Order not found.');
        }

        $this->authorize('update', $order);

        $validated = $request->validate([
            'customer_id'        => ['required', 'exists:customers,id'],
            'quotation_id'       => ['nullable', 'exists:quotations,id'],
            'sales_person_id'    => ['nullable', 'exists:users,id'],
            'sales_order_number' => ['required', 'string', 'max:255'],
            'order_date'         => ['required', 'date'],
            'shipment_date'      => ['nullable', 'date', 'after_or_equal:order_date'],
            'payment_terms'      => ['nullable', 'string', 'max:255'],
            'billing_address'    => ['nullable', 'string'],
            'shipping_address'   => ['nullable', 'string'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'shipping_charges'   => ['nullable', 'numeric', 'min:0'],
            'adjustment'         => ['nullable', 'numeric'],
            'terms_conditions'   => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'items.*.product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'items.*.warehouse_id'=> ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.item_name'   => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->salesOrders->update($order, $validated, $request->input('items', []));

        return redirect()
            ->route('sales.orders.show', $order->id)
            ->with('success', 'Sales Order successfully updated!');
    }

    public function confirm(int $id): RedirectResponse
    {
        $order = $this->salesOrders->find($id);

        if (!$order) {
            abort(404, 'Sales Order not found.');
        }

        $this->authorize('confirm', $order);

        if ($order->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only Draft Sales Orders can be confirmed.']);
        }

        // Confirm the Sales Order and set planning to Pending
        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'Confirmed',
                'planning_status' => 'Pending'
            ]);
        });

        return redirect()->route('sales.orders.plan', $order->id)->with('success', 'Sales Order confirmed successfully! Please configure replenishment planning.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $order = $this->salesOrders->find($id);

        if (!$order) {
            abort(404, 'Sales Order not found.');
        }

        $this->authorize('cancel', $order);

        if ($order->status === 'Shipped') {
            return back()->withErrors(['status' => 'Cannot cancel a Shipped Sales Order.']);
        }

        // Release reservations if it was Confirmed/Partially Shipped
        if (in_array($order->status, ['Confirmed', 'Partially Shipped'])) {
            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    if (!$item->product_id || $item->product->type === 'Service') continue;

                    StockService::releaseStock(
                        $order->tenant_id,
                        $item->product_id,
                        $item->warehouse_id,
                        $item->quantity,
                        'SalesOrder',
                        $order->id,
                        $item->id
                    );
                }
            });
        }

        $order->update(['status' => 'Cancelled']);

        return back()->with('success', 'Sales Order cancelled successfully. Reservations released.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $order = $this->salesOrders->find($id);

        if (!$order) {
            abort(404, 'Sales Order not found.');
        }

        $this->authorize('delete', $order);

        $this->salesOrders->delete($order);

        return redirect()
            ->route('sales.orders.index')
            ->with('success', 'Sales Order deleted successfully.');
    }

    public function plan(int $id): \Illuminate\View\View
    {
        $order = SalesOrder::with(['items.product', 'customer', 'productionOrders', 'purchaseRequisitions.items', 'stockAllocations'])->findOrFail($id);

        $warehouses = \App\Domains\Inventory\Models\Warehouse::query()->orderBy('name')->get();

        $planningItems = [];
        foreach ($order->items as $item) {
            if (!$item->product_id || $item->product->type === 'Service') continue;

            $ordered = floatval($item->quantity);

            // Fetch available stock per warehouse for this product
            $warehouseStocks = [];
            foreach ($warehouses as $wh) {
                $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::query()
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $wh->id)
                    ->first();
                
                $physical = $stock ? floatval($stock->quantity) : 0.0;
                $reserved = $stock ? floatval($stock->reserved_qty) : 0.0;

                // Reserved by this SO item specifically
                $reservedByThis = \App\Domains\Inventory\Models\StockReservation::query()
                    ->where('tenant_id', $order->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $wh->id)
                    ->where('reference_type', 'SalesOrder')
                    ->where('reference_id', $order->id)
                    ->where('reference_item_id', $item->id)
                    ->where('status', 'Active')
                    ->sum('reserved_qty');

                $otherReserved = max(0.0, $reserved - $reservedByThis);
                $available = max(0.0, $physical - $otherReserved);

                $warehouseStocks[$wh->id] = [
                    'warehouse_name' => $wh->name,
                    'available' => $available,
                    'reserved_by_this' => $reservedByThis,
                ];
            }

            // Existing allocations
            $savedAllocations = $order->stockAllocations->where('sales_order_item_id', $item->id);

            // Existing MOs & PR items
            $existingMo = $order->productionOrders->where('sales_order_item_id', $item->id)->first();
            $existingPrItem = null;
            foreach ($order->purchaseRequisitions as $pr) {
                $found = $pr->items->where('sales_order_item_id', $item->id)->first();
                if ($found) {
                    $existingPrItem = $found;
                    break;
                }
            }

            $planningItems[] = [
                'item' => $item,
                'ordered' => $ordered,
                'warehouse_stocks' => $warehouseStocks,
                'saved_allocations' => $savedAllocations,
                'existing_mo' => $existingMo,
                'existing_pr_item' => $existingPrItem,
            ];
        }

        return view('modules.sales.orders.plan', [
            'order' => $order,
            'warehouses' => $warehouses,
            'planningItems' => $planningItems,
        ]);
    }

    public function processPlan(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $order = SalesOrder::with(['items.product'])->findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.action' => 'required|string|in:Manufacture,Purchase,None',
        ]);

        DB::transaction(function () use ($order, $validated) {
            $purchaseItems = [];

            // 1. Release active reservations first for complete re-calculation safety
            foreach ($order->items as $soItem) {
                if (!$soItem->product_id || $soItem->product->type === 'Service') continue;

                \App\Domains\Inventory\Models\StockReservation::query()
                    ->where('tenant_id', $order->tenant_id)
                    ->where('product_id', $soItem->product_id)
                    ->where('reference_type', 'SalesOrder')
                    ->where('reference_id', $order->id)
                    ->where('reference_item_id', $soItem->id)
                    ->where('status', 'Active')
                    ->get()
                    ->each(function ($res) use ($order, $soItem) {
                        StockService::releaseStock(
                            $order->tenant_id,
                            $soItem->product_id,
                            $res->warehouse_id,
                            $res->reserved_qty,
                            'SalesOrder',
                            $order->id,
                            $soItem->id
                        );
                    });
            }

            // 2. Clear old allocations records
            \App\Domains\Sales\Models\SalesOrderAllocation::where('sales_order_id', $order->id)->delete();

            // 3. Process allocations, reserve stock, split shortages
            foreach ($validated['items'] as $itemData) {
                $itemId = $itemData['sales_order_item_id'];
                $warehouseId = $itemData['warehouse_id'] ?? null;
                $action = $itemData['action'];

                $soItem = $order->items->where('id', $itemId)->first();
                if (!$soItem) continue;

                $methods = [];
                $reserveQty = 0.0;

                // Reserve from the selected warehouse up to available stock limit
                if ($warehouseId) {
                    $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::query()
                        ->where('product_id', $soItem->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    $physical = $stock ? floatval($stock->quantity) : 0.0;
                    $reserved = $stock ? floatval($stock->reserved_qty) : 0.0;

                    $available = max(0.0, $physical - $reserved);
                    $reserveQty = min(floatval($soItem->quantity), $available);

                    if ($reserveQty > 0) {
                        // Create Allocation record
                        \App\Domains\Sales\Models\SalesOrderAllocation::create([
                            'tenant_id' => $order->tenant_id,
                            'sales_order_id' => $order->id,
                            'sales_order_item_id' => $soItem->id,
                            'warehouse_id' => $warehouseId,
                            'reserved_qty' => $reserveQty,
                        ]);

                        // Call reserve stock service
                        StockService::reserveStock(
                            $order->tenant_id,
                            $soItem->product_id,
                            $warehouseId,
                            $reserveQty,
                            'SalesOrder',
                            $order->id,
                            $soItem->id
                        );

                        $methods[] = 'Stock';
                    }
                }

                $shortage = max(0.0, floatval($soItem->quantity) - $reserveQty);
                $fallbackWarehouseId = $warehouseId ?: \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $order->tenant_id)->first()?->id;

                // MO Generation
                if ($shortage > 0 && $action === 'Manufacture') {
                    $methods[] = 'Manufacture';
                    $moExists = \App\Domains\Production\Models\ProductionOrder::where('sales_order_item_id', $soItem->id)->exists();
                    if (!$moExists) {
                        $bom = \App\Domains\Production\Models\ProductionBom::where('product_id', $soItem->product_id)
                            ->where('status', 'active')
                            ->first();

                        $orderNumber = app(\App\Domains\Production\Services\ProductionOrderNumberService::class)
                            ->generateNextNumber($order->tenant_id);

                        \App\Domains\Production\Models\ProductionOrder::create([
                            'tenant_id' => $order->tenant_id,
                            'order_number' => $orderNumber,
                            'product_id' => $soItem->product_id,
                            'bom_id' => $bom?->id,
                            'quantity_ordered' => $shortage,
                            'status' => 'draft',
                            'start_date' => now(),
                            'end_date' => now()->addDays(7),
                            'sales_order_id' => $order->id,
                            'sales_order_item_id' => $soItem->id,
                        ]);
                    }
                }

                // PR Generation gathering
                if ($shortage > 0 && $action === 'Purchase') {
                    $methods[] = 'Purchase';
                    $purchaseItems[] = [
                        'sales_order_item_id' => $soItem->id,
                        'product_id' => $soItem->product_id,
                        'warehouse_id' => $fallbackWarehouseId,
                        'quantity' => $shortage,
                        'unit_cost' => $soItem->product->unit_cost ?: 0.00,
                    ];
                }

                // Update item override label
                $soItem->update([
                    'fulfillment_method' => empty($methods) ? 'None' : implode(' + ', array_unique($methods))
                ]);
            }

            // Generate PR
            if (!empty($purchaseItems)) {
                $latestPr = \App\Domains\Purchase\Models\PurchaseRequisition::latest('id')->first();
                $nextPrSeq = $latestPr ? intval(str_replace('PR-', '', $latestPr->requisition_number)) + 1 : 1;
                $prNumber = 'PR-' . str_pad($nextPrSeq, 4, '0', STR_PAD_LEFT);

                $pr = \App\Domains\Purchase\Models\PurchaseRequisition::create([
                    'tenant_id' => $order->tenant_id,
                    'requisition_number' => $prNumber,
                    'requested_by' => auth()->id(),
                    'requisition_date' => now(),
                    'status' => 'Draft',
                    'sales_order_id' => $order->id,
                ]);

                foreach ($purchaseItems as $pItem) {
                    \App\Domains\Purchase\Models\PurchaseRequisitionItem::create([
                        'purchase_requisition_id' => $pr->id,
                        'sales_order_item_id' => $pItem['sales_order_item_id'],
                        'product_id' => $pItem['product_id'],
                        'warehouse_id' => $pItem['warehouse_id'],
                        'quantity' => $pItem['quantity'],
                        'estimated_cost' => floatval($pItem['unit_cost']) * floatval($pItem['quantity']),
                    ]);
                }
            }

            // Update planning status to Completed
            $order->update(['planning_status' => 'Completed']);
        });

        return redirect()
            ->route('sales.orders.show', $order->id)
            ->with('success', 'Replenishment plan executed successfully! Stock allocations and PR/MO documents generated.');
    }
}
