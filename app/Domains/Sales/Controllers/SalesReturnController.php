<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Models\SalesReturnItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Repositories\SalesReturnRepository;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function __construct(
        private readonly SalesReturnRepository $returnRepo
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesReturn::class);
        $returns = $this->returnRepo->getPaginated($request->all(), 15);

        return view('modules.sales.returns.index', compact('returns'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesReturn::class);

        $salesOrderId = $request->input('sales_order_id');
        $salesOrder = null;
        if ($salesOrderId) {
            $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->find($salesOrderId);
        }

        $tenantId = require_tenant_id();
        $customers = Customer::query()->orderBy('name')->get();
        $salesOrders = SalesOrder::with(['invoices' => function($q) {
            $q->where('status', '!=', 'Cancelled')->with('items.product', 'items.warehouse');
        }, 'customer', 'items.product', 'items.warehouse'])
        ->whereIn('status', ['Confirmed', 'Partially Shipped', 'Shipped', 'Invoiced'])
        ->latest()->get();

        $invoices = Invoice::with(['customer', 'items.product', 'items.warehouse', 'salesOrder'])
            ->where('status', '!=', 'Cancelled')
            ->latest()->get();

        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get();
        $products = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->orderBy('name')->get();

        $latest = SalesReturn::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('RET-', '', $latest->return_number)) + 1 : 1;
        $nextReturnNumber = 'RET-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $prefillSalesOrderId = $salesOrderId;
        $prefillCustomerId   = $salesOrder?->customer_id;
        $prefillInvoiceId    = $request->input('invoice_id');

        $formattedWarehouses = $warehouses->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values()->all();
        $formattedProducts   = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?: '',
            'selling_price' => (float)($p->selling_price ?? 0),
            'track_serial_number' => (bool) $p->track_serial_number,
        ])->values()->all();

        return view('modules.sales.returns.create', compact(
            'customers', 'salesOrders', 'salesOrder', 'invoices', 'warehouses', 'nextReturnNumber',
            'prefillSalesOrderId', 'prefillCustomerId', 'prefillInvoiceId', 'products', 'formattedProducts', 'formattedWarehouses'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SalesReturn::class);

        // Auto-fill sales_order_id and customer_id from Invoice if provided
        if ($request->filled('invoice_id')) {
            $inv = Invoice::find($request->input('invoice_id'));
            if ($inv) {
                if (!$request->filled('customer_id')) {
                    $request->merge(['customer_id' => $inv->customer_id]);
                }
                if (!$request->filled('sales_order_id') && $inv->sales_order_id) {
                    $request->merge(['sales_order_id' => $inv->sales_order_id]);
                }
            }
        }

        // Auto-fill customer_id from SalesOrder if missing in request
        if (!$request->filled('customer_id') && $request->filled('sales_order_id')) {
            $so = SalesOrder::find($request->input('sales_order_id'));
            if ($so) {
                $request->merge(['customer_id' => $so->customer_id]);
            }
        }

        // Auto-fill warehouse_id for return items if missing/invalid
        if ($request->has('items') && is_array($request->input('items'))) {
            $defaultWhId = Warehouse::query()->orderByDesc('is_default')->orderBy('name')->first()?->id;
            if (!$defaultWhId) {
                $defaultWhId = Warehouse::create([
                    'tenant_id' => tenant_id() ?? 1,
                    'name'      => 'Main Warehouse',
                    'code'      => 'WH-MAIN',
                    'is_default'=> true
                ])->id;
            }

            $items = $request->input('items');
            foreach ($items as &$it) {
                if (empty($it['warehouse_id']) || !Warehouse::where('id', $it['warehouse_id'])->exists()) {
                    $it['warehouse_id'] = $defaultWhId;
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'invoice_id'     => ['nullable', 'exists:invoices,id'],
            'return_number'  => ['required', 'string', 'max:255', 'unique:sales_returns,return_number'],
            'return_date'    => ['required', 'date'],
            'reason'         => ['nullable', 'string'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.warehouse_id' => ['required', 'exists:warehouses,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.serial_numbers' => ['nullable', 'string'],
        ]);

        $returnOrder = DB::transaction(function () use ($validated) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += floatval($item['quantity']) * floatval($item['unit_price']);
            }

            $salesReturn = SalesReturn::create([
                'tenant_id'           => tenant_id() ?? 1,
                'customer_id'         => $validated['customer_id'],
                'sales_order_id'      => $validated['sales_order_id'] ?? null,
                'invoice_id'          => $validated['invoice_id'] ?? null,
                'return_number'       => $validated['return_number'],
                'return_date'         => $validated['return_date'],
                'status'              => 'Pending',
                'reason'              => $validated['reason'] ?? null,
                'total_amount'        => $totalAmount,
                'total_refund_amount' => $totalAmount,
            ]);

            foreach ($validated['items'] as $item) {
                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id'      => $item['product_id'],
                    'warehouse_id'    => $item['warehouse_id'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'total_amount'    => floatval($item['quantity']) * floatval($item['unit_price']),
                    'serial_numbers'  => $item['serial_numbers'] ?? null,
                ]);
            }

            return $salesReturn;
        });

        return redirect()->route('sales.returns.show', $returnOrder->id)->with('success', "Sales Return {$returnOrder->return_number} recorded successfully.");
    }

    public function show(int $id): View
    {
        $return = $this->returnRepo->find($id);
        if (!$return) abort(404);
        $this->authorize('view', $return);

        return view('modules.sales.returns.show', compact('return'));
    }

    public function approve(int $id): RedirectResponse
    {
        $returnOrder = $this->returnRepo->find($id);
        if (!$returnOrder) abort(404);
        $this->authorize('update', $returnOrder);

        if (!in_array($returnOrder->status, ['Pending', 'Draft'])) {
            return redirect()->back()->with('error', 'Only Pending or Draft Sales Returns can be approved.');
        }

        DB::transaction(function () use ($returnOrder) {
            $tenantId = $returnOrder->tenant_id ?: (tenant_id() ?? 1);

            foreach ($returnOrder->items as $item) {
                $serials = [];
                if (!empty($item->serial_numbers)) {
                    $serials = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $item->serial_numbers)));
                }

                // Determine actual Inventory Cost Price (Cost of Goods Sold valuation) for restocking
                $costPrice = 0.0;

                // 1. If Serial-tracked, fetch exact Purchase Rate from original Serial Number entry
                if (!empty($serials)) {
                    $snRate = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->whereIn('serial_number', $serials)
                        ->whereNotNull('purchase_rate')
                        ->where('purchase_rate', '>', 0)
                        ->avg('purchase_rate');

                    if ($snRate && $snRate > 0) {
                        $costPrice = (float)$snRate;
                    }
                }

                // 2. If returning against a Sales Order, fetch exact Outflow Unit Cost from original Order dispatch
                if ($costPrice <= 0 && $returnOrder->sales_order_id) {
                    $soOutTx = \App\Domains\Inventory\Models\StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('reference_type', 'SalesOrder')
                        ->where('reference_id', $returnOrder->sales_order_id)
                        ->first();

                    if ($soOutTx && (float)$soOutTx->unit_cost > 0) {
                        $costPrice = (float)$soOutTx->unit_cost;
                    }
                }

                // 3. Check current Warehouse moving average cost
                if ($costPrice <= 0) {
                    $whStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->first();
                    if ($whStock && (float)$whStock->unit_cost > 0) {
                        $costPrice = (float)$whStock->unit_cost;
                    }
                }

                // 4. Check Product Master Opening Stock Rate or Cost Price
                if ($costPrice <= 0) {
                    $product = \App\Domains\Inventory\Models\Product::find($item->product_id);
                    if ($product) {
                        if ((float)($product->opening_stock_rate ?? 0) > 0) {
                            $costPrice = (float)$product->opening_stock_rate;
                        } elseif ((float)($product->cost_price ?? $product->unit_cost) > 0) {
                            $costPrice = (float)($product->cost_price ?: $product->unit_cost);
                        }
                    }
                }

                // 5. Safe Fallback
                if ($costPrice <= 0) {
                    $costPrice = (float)$item->unit_price;
                }

                \App\Domains\Inventory\Services\StockService::recordInflow(
                    $tenantId,
                    (int)$item->product_id,
                    (int)$item->warehouse_id,
                    (float)$item->quantity,
                    $costPrice,
                    'SalesReturn',
                    (int)$returnOrder->id,
                    null,
                    $serials
                );
            }

            if ($returnOrder->invoice_id) {
                $invoice = \App\Domains\Sales\Models\Invoice::find($returnOrder->invoice_id);
                if ($invoice) {
                    $apply = min((float) $returnOrder->total_refund_amount, (float) $invoice->balance_due);
                    $invoice->balance_due = max(0, (float) $invoice->balance_due - $apply);
                    $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
                    $invoice->save();
                }
            }

            $returnOrder->update(['status' => 'Completed']);
        });

        event(new \App\Domains\Sales\Events\SalesReturnApproved($returnOrder));

        return redirect()->route('sales.returns.show', $returnOrder->id)->with('success', "Sales Return {$returnOrder->return_number} approved and stock restored to inventory successfully.");
    }
}
