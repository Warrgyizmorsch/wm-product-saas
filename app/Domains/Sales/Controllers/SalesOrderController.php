<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Services\SalesOrderService;
use App\Domains\Sales\Services\MaterialRequirementService;
use App\Domains\Sales\Repositories\SalesOrderRepository;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Platform\Models\PaymentTerm;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\SalesOrderExport;
use Maatwebsite\Excel\Facades\Excel;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderRepository $orderRepo,
        private readonly SalesOrderService $salesOrders,
        private readonly MaterialRequirementService $materialRequirementService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesOrder::class);
        $orders = $this->orderRepo->getPaginatedOrders($request->all(), 10);

        return view('modules.sales.orders.index', compact('orders'));
    }

    /**
     * Export Sales Orders to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', SalesOrder::class);
        $tenantId = tenant_id() ?? auth()->user()->tenant_id ?? 1;

        return Excel::download(
            new SalesOrderExport($tenantId, $request->all()),
            'sales_orders_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesOrder::class);

        $prefillQuotation = null;
        if ($request->has('quotation_id')) {
            $prefillQuotation = Quotation::query()
                ->with(['items.product', 'lead', 'crmAccount', 'crmDeal.account', 'crmDeal.contact'])
                ->find($request->input('quotation_id'));

            if ($prefillQuotation) {
                // Ensure customer object & ID are resolved/synced for the quotation
                $prefillCustomer = $prefillQuotation->customer;
            }
        }

        $customers = Customer::query()->orderBy('name')->get();
        $products = Product::query()->where('status', 'active')->get();
        $salesReps = User::query()->orderBy('name')->get();
        
        $quotations = Quotation::query()
            ->where('is_current', true)
            ->latest()
            ->get();

        $warehouses = Warehouse::query()->orderBy('name')->get();
        $paymentTerms = PaymentTerm::query()->where('is_active', true)->orderBy('due_days')->get();

        return view('modules.sales.orders.create', [
            'customers' => $customers,
            'products' => $products,
            'warehouses' => $warehouses,
            'salesReps' => $salesReps,
            'quotations' => $quotations,
            'paymentTerms' => $paymentTerms,
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
            'discount_type'      => ['nullable', 'string', 'in:without_discount,item_wise,order_wise'],
            'tax_type'           => ['nullable', 'string', 'in:without_tax,item_wise_tax,order_wise_tax'],
            'gst_type'           => ['nullable', 'string', 'in:cgst_sgst,igst'],
            'order_tax_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'shipping_charges'   => ['nullable', 'numeric', 'min:0'],
            'freight_terms'      => ['nullable', 'string', 'in:To Pay,To Be Billed,Prepaid,Customer Pickup'],
            'freight_amount'     => ['nullable', 'numeric', 'min:0'],
            'freight_tax_rate'   => ['nullable', 'string'],
            'adjustment'         => ['nullable', 'numeric'],
            'terms_conditions'   => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.warehouse_id'=> ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.item_name'   => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.*.product_id.required' => 'Please select a valid Product from the dropdown for each item line.',
            'items.*.product_id.exists'   => 'The selected product does not exist in inventory.',
        ]);

        $order = $this->salesOrders->create($validated, $request->input('items', []));

        // Trigger Notification Master Rules
        \App\Domains\Platform\Services\NotificationRuleService::trigger('sales.order.created', [
            'doc_no'             => $order->sales_order_number,
            'customer_name'      => $order->customer?->name ?? 'Customer',
            'amount'             => format_currency($order->total_amount ?? 0),
            'created_by'         => auth()->user()?->name ?? 'User',
            'created_by_user_id' => auth()->id(),
            'assigned_user_id'   => $order->sales_person_id,
            'date'               => now()->format('d M Y h:i A'),
        ], route('sales.orders.show', $order->id), $order);

        return redirect()->route('sales.orders.show', $order->id)->with('success', 'Sales Order successfully created!');
    }

    public function show(int $id): View
    {
        $order = $this->orderRepo->findWithDetails($id);
        $this->authorize('view', $order);

        return view('modules.sales.orders.show', compact('order'));
    }

    public function downloadPdf(int $id)
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404, 'Sales Order not found.');
        $this->authorize('view', $order);

        $pdf = Pdf::loadView('modules.sales.orders.pdf', compact('order'));
        return $pdf->download("SalesOrder_{$order->sales_order_number}.pdf");
    }

    public function edit(int $id): View
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404, 'Sales Order not found.');
        $this->authorize('update', $order);

        $customers = Customer::query()->orderBy('name')->get();
        $products = Product::query()->where('status', 'active')->get();
        $salesReps = User::query()->orderBy('name')->get();
        $quotations = Quotation::query()->where('is_current', true)->whereIn('status', ['Approved', 'Accepted'])->latest()->get();
        $warehouses = Warehouse::query()->orderBy('name')->get();
        $paymentTerms = PaymentTerm::query()->where('is_active', true)->orderBy('due_days')->get();

        return view('modules.sales.orders.edit', compact('order', 'customers', 'products', 'warehouses', 'salesReps', 'quotations', 'paymentTerms'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404, 'Sales Order not found.');
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
            'discount_type'      => ['nullable', 'string', 'in:without_discount,item_wise,order_wise'],
            'tax_type'           => ['nullable', 'string', 'in:without_tax,item_wise_tax,order_wise_tax'],
            'gst_type'           => ['nullable', 'string', 'in:cgst_sgst,igst'],
            'order_tax_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'shipping_charges'   => ['nullable', 'numeric', 'min:0'],
            'freight_terms'      => ['nullable', 'string', 'in:To Pay,To Be Billed,Prepaid,Customer Pickup'],
            'freight_amount'     => ['nullable', 'numeric', 'min:0'],
            'freight_tax_rate'   => ['nullable', 'string'],
            'adjustment'         => ['nullable', 'numeric'],
            'terms_conditions'   => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.warehouse_id'=> ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.item_name'   => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.*.product_id.required' => 'Please select a valid Product from the dropdown for each item line.',
            'items.*.product_id.exists'   => 'The selected product does not exist in inventory.',
        ]);

        $this->salesOrders->update($order, $validated, $request->input('items', []));

        return redirect()->route('sales.orders.show', $order->id)->with('success', 'Sales Order successfully updated!');
    }

    public function confirm(int $id): RedirectResponse
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404, 'Sales Order not found.');
        $this->authorize('confirm', $order);

        if ($order->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only Draft Sales Orders can be confirmed.']);
        }

        $nextRequirementNumber = $this->materialRequirementService->getNextRequirementNumber();
        $this->orderRepo->confirmOrder($order, $nextRequirementNumber);

        // Trigger Notification Master Rules
        \App\Domains\Platform\Services\NotificationRuleService::trigger('sales.order.confirmed', [
            'doc_no'             => $order->sales_order_number,
            'customer_name'      => $order->customer?->name ?? 'Customer',
            'amount'             => format_currency($order->total_amount ?? 0),
            'confirmed_by'       => auth()->user()?->name ?? 'User',
            'created_by_user_id' => $order->created_by,
            'assigned_user_id'   => $order->sales_person_id,
            'date'               => now()->format('d M Y h:i A'),
        ], route('sales.orders.show', $order->id), $order);

        return redirect()->route('sales.orders.show', $order->id)->with('success', 'Sales Order confirmed and Material Requirement generated successfully!');
    }

    public function cancel(int $id): RedirectResponse
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404, 'Sales Order not found.');
        $this->authorize('cancel', $order);

        if ($order->status === 'Shipped') {
            return back()->withErrors(['status' => 'Cannot cancel a Shipped Sales Order.']);
        }

        $this->orderRepo->cancelOrder($order);

        return back()->with('success', 'Sales Order cancelled successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $order = $this->orderRepo->find($id);
        if (!$order) abort(404, 'Sales Order not found.');
        $this->authorize('delete', $order);

        $this->salesOrders->delete($order);

        return redirect()->route('sales.orders.index')->with('success', 'Sales Order deleted successfully.');
    }
}
