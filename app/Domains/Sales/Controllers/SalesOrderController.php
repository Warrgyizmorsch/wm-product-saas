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
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrders,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesOrder::class);

        $query = SalesOrder::query()->with(['customer', 'quotation']);

        // Search Keywords
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('sales_order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($custQ) use ($search) {
                      $custQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'order_date');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['sales_order_number', 'order_date', 'total_amount', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $orders = $query->paginate(10)->withQueryString();

        return view('modules.sales.orders.index', compact('orders'));
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
            'materialRequirements.items', 
            'invoices.items', 
            'allocations.payment', 
            'returns.items',
            'productionOrders.product',
        ])->findOrFail($id);

        $this->authorize('view', $order);

        return view('modules.sales.orders.show', [
            'order' => $order,
        ]);
    }

    public function downloadPdf(int $id)
    {
        $order = SalesOrder::with([
            'customer', 
            'salesPerson', 
            'quotation', 
            'items.product', 
            'items.warehouse',
        ])->findOrFail($id);

        $this->authorize('view', $order);

        $pdf = Pdf::loadView('modules.sales.orders.pdf', [
            'order' => $order,
        ]);

        return $pdf->download("SalesOrder_{$order->sales_order_number}.pdf");
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

        // Confirm the Sales Order
        $delivery = DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'Confirmed',
            ]);

            // Auto-create one Material Requirement linked to the Sales Order
            $delivery = MaterialRequirement::create([
                'tenant_id' => $order->tenant_id,
                'sales_order_id' => $order->id,
                'requirement_number' => app(\App\Domains\Sales\Services\MaterialRequirementService::class)->getNextRequirementNumber(),
                'requirement_date' => now(),
                'status' => 'Pending',
            ]);

            $defaultWarehouseId = Warehouse::where('tenant_id', $order->tenant_id)
                ->orderBy('is_default', 'desc')
                ->first()?->id ?? 1;

            foreach ($order->items as $soItem) {
                MaterialRequirementItem::create([
                    'material_requirement_id' => $delivery->id,
                    'sales_order_item_id' => $soItem->id,
                    'product_id' => $soItem->product_id,
                    'warehouse_id' => $soItem->warehouse_id ?? $defaultWarehouseId,
                    'quantity' => $soItem->quantity,
                    'quantity_ordered' => $soItem->quantity,
                    'quantity_reserved' => 0.0000,
                    'status' => 'Pending',
                ]);
            }

            return $delivery;
        });

        return redirect()->route('sales.orders.show', $order->id)
            ->with('success', 'Sales Order confirmed and Material Requirement generated successfully!');
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

        $order->update(['status' => 'Cancelled']);

        return back()->with('success', 'Sales Order cancelled successfully.');
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
