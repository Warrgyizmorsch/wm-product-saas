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
            ->whereIn('type', ['finished_good', 'component'])
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
        $order = $this->salesOrders->find($id);

        if (!$order) {
            abort(404, 'Sales Order not found.');
        }

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
            ->whereIn('type', ['finished_good', 'component'])
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

        // Validate warehouse allocation
        $errors = [];
        foreach ($order->items as $item) {
            if (!$item->product_id || $item->product->type === 'Service') continue;

            if (!$item->warehouse_id) {
                $errors[] = "Warehouse must be allocated for product line: {$item->product->name}";
                continue;
            }
        }

        if (!empty($errors)) {
            return back()->withErrors($errors);
        }

        // Confirm the Sales Order without reservation
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'Confirmed']);
        });

        return back()->with('success', 'Sales Order confirmed successfully!');
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
}
