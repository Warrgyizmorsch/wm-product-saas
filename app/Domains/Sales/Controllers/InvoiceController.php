<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Repositories\InvoiceRepository;
use App\Domains\Platform\Models\PaymentTerm;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\VoucherService;
use App\Domains\Accounting\Support\VoucherType;
use App\Domains\Sales\Models\DispatchOrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\InvoiceExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepo
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = $this->invoiceRepo->getPaginated($request->all(), 15);

        return view('modules.sales.invoices.index', compact('invoices'));
    }

    /**
     * Export Invoices to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        $tenantId = tenant_id() ?? auth()->user()->tenant_id ?? 1;

        return Excel::download(
            new InvoiceExport($tenantId, $request->all()),
            'invoices_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        $customerId = $request->input('customer_id') ?: $request->query('customer_id');
        $requestedMode = $request->input('mode') ?: $request->input('amp;mode') ?: $request->query('mode') ?: $request->query('amp;mode');
        $salesOrderId = $request->input('sales_order_id') ?: $request->input('amp;sales_order_id') ?: $request->query('sales_order_id') ?: $request->query('amp;sales_order_id');
        $dispatchOrderId = $request->input('dispatch_order_id') ?? $request->input('dispatch_id') ?? $request->input('material_requirement_id') ?? $request->input('amp;dispatch_order_id') ?? $request->query('dispatch_order_id');

        $tenantSettings = tenant()?->settings ?? [];
        $invoicingPolicy = $tenantSettings['invoicing_policy'] ?? 'both';

        if ($invoicingPolicy === 'sales_order') {
            $mode = 'sales_order';
        } elseif ($invoicingPolicy === 'dispatch_order') {
            $mode = 'dispatch_order';
        } else {
            $mode = ($requestedMode === 'dispatch_order') ? 'dispatch_order' : 'sales_order';
        }

        // Fetch Sales Orders that have unbilled quantities remaining
        $allSalesOrders = SalesOrder::with(['customer', 'items.product', 'items.warehouse', 'invoices' => function($q) {
            $q->where('status', '!=', 'Cancelled')->with('items');
        }])
        ->whereIn('status', ['Confirmed', 'Partially Shipped', 'Shipped', 'Invoiced', 'Partially Delivered', 'Delivered'])
        ->latest()->get();

        $salesOrders = $allSalesOrders->filter(function ($so) {
            $totalOrdered = $so->items->sum('quantity');
            $totalInvoiced = $so->invoices->flatMap->items->where('sales_order_item_id', '!=', null)->sum('quantity');
            return ($totalOrdered - $totalInvoiced) > 0.0001;
        })->values();

        // Fetch Dispatch Orders that have status Confirmed, Shipped, Dispatched, or Delivered and have unbilled quantities remaining
        $allDispatchOrders = \App\Domains\Sales\Models\DispatchOrder::with(['salesOrder.customer', 'items.product', 'items.warehouse', 'materialRequirement', 'invoice'])
            ->whereIn('status', ['Confirmed', 'Shipped', 'Dispatched', 'Delivered'])
            ->latest()->get();

        $dispatchOrders = $allDispatchOrders->filter(function ($do) {
            if (!empty($do->invoice_id) || in_array($do->status, ['Invoiced', 'Fully Invoiced', 'Completed', 'Cancelled'])) {
                if ($do->invoice && $do->invoice->status === 'Cancelled') {
                    return true;
                }
                return false;
            }

            return true;
        });

        if ($salesOrderId) {
            $dispatchOrders = $dispatchOrders->filter(fn($do) => $do->sales_order_id == $salesOrderId)->values();
        }

        $customers  = \App\Domains\CRM\Models\Customer::query()->orderBy('name')->get();
        $products   = \App\Domains\Inventory\Models\Product::where('status', 'active')->orderBy('name')->get();
        $warehouses = Warehouse::query()->orderBy('name')->get();

        $dispatchOrder = null;
        $salesOrder = null;
        $materialRequirement = null;

        if ($requestedMode === 'dispatch' || $requestedMode === 'dispatch_order') {
            $mode = 'dispatch_order';
        } elseif ($requestedMode === 'sales_order' || $requestedMode === 'so') {
            $mode = 'sales_order';
        }

        if ($salesOrderId && !$dispatchOrderId) {
            $mode = 'sales_order';
        }

        if ($dispatchOrderId) {
            $matchingDO = $dispatchOrders->firstWhere('id', (int)$dispatchOrderId) 
                ?: $dispatchOrders->firstWhere('material_requirement_id', (int)$dispatchOrderId);
            if ($matchingDO) {
                $dispatchOrder = $matchingDO;
                $salesOrderId = $dispatchOrder->sales_order_id;
                $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->find($salesOrderId);
                if (!$requestedMode) {
                    $mode = 'dispatch_order';
                }
            } else {
                $dispatchOrder = $dispatchOrders->first() ?: null;
                if ($dispatchOrder) {
                    $salesOrderId = $dispatchOrder->sales_order_id;
                    $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->find($salesOrderId);
                }
            }
        }

        if (!$dispatchOrderId && $mode !== 'dispatch_order') {
            if ($salesOrderId) {
                $matchingSO = $salesOrders->firstWhere('id', (int)$salesOrderId)
                    ?: SalesOrder::with(['customer', 'items.product', 'items.warehouse'])->find((int)$salesOrderId);
                if ($matchingSO) {
                    $salesOrder = $matchingSO;
                } else {
                    $salesOrder = $salesOrders->first() ?: null;
                    $salesOrderId = $salesOrder?->id;
                }
            } elseif ($salesOrders->isNotEmpty()) {
                $salesOrder = $salesOrders->first();
                $salesOrderId = $salesOrder?->id;
            }
        }

        $tenantId = require_tenant_id();
        $latest = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('INV-', '', $latest->invoice_number)) + 1 : 1;
        $nextInvoiceNumber = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        while (Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('invoice_number', $nextInvoiceNumber)->exists()) {
            $nextSeq++;
            $nextInvoiceNumber = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }

        $advanceAllocations = 0;
        if ($salesOrder) {
            $advanceAllocations = PaymentAllocation::where('sales_order_id', $salesOrder->id)
                ->whereNull('invoice_id')
                ->sum('allocated_amount');
        }

        $taxMode = $salesOrder?->effective_tax_mode ?? 'exclusive';

        $invoiceItems = [];
        if ($mode === 'dispatch_order') {
            if ($dispatchOrder) {
                foreach ($dispatchOrder->items as $doItem) {
                    $soItem = $salesOrder?->items->firstWhere('product_id', $doItem->product_id);
                    $dispatchedQty = floatval($doItem->quantity_dispatched > 0 ? $doItem->quantity_dispatched : $doItem->quantity_ordered);

                    $cumulativeDispatched = \App\Domains\Sales\Models\DispatchOrderItem::whereHas('dispatchOrder', function($q) use ($dispatchOrder) {
                        $q->where('sales_order_id', $dispatchOrder->sales_order_id)
                          ->where('id', '<=', $dispatchOrder->id);
                    })->where(function($q) use ($doItem) {
                        if ($doItem->material_requirement_item_id) {
                            $q->where('material_requirement_item_id', $doItem->material_requirement_item_id);
                        } else {
                            $q->where('product_id', $doItem->product_id);
                        }
                    })->sum(DB::raw('COALESCE(NULLIF(quantity_dispatched, 0), quantity_ordered)'));

                    $mrItemId = $doItem->material_requirement_item_id;
                    $alreadyInvoiced = InvoiceItem::whereHas('invoice', fn($q) => $q->where('status', '!=', 'Cancelled')->where('sales_order_id', $dispatchOrder->sales_order_id))
                        ->where(function($q) use ($mrItemId, $doItem) {
                            if ($mrItemId) {
                                $q->where('material_requirement_item_id', $mrItemId);
                            } else {
                                $q->where('product_id', $doItem->product_id);
                            }
                        })
                        ->sum('quantity');

                    $unbilledQty = min($dispatchedQty, max(0, $cumulativeDispatched - $alreadyInvoiced));
                    if ($unbilledQty <= 0.0001) {
                        continue; // Skip items already fully invoiced
                    }

                    $unitPriceInBase = $soItem ? floatval($soItem->unit_price) : floatval($doItem->product?->selling_price ?? 0);
                    $unitPrice = round(convert_from_base($unitPriceInBase), 2);
                    $taxRate = $soItem ? floatval($soItem->tax_rate) : 0.0;
                    $discountInBase = $soItem ? floatval($soItem->discount) : 0.0;
                    $discount = round(convert_from_base($discountInBase), 2);

                    $subtotal = $unbilledQty * $unitPrice;
                    $discountAmt = $discount;
                    $net = max(0, $subtotal - $discountAmt);

                    if ($taxMode === 'inclusive') {
                        $taxable = ($taxRate > 0) ? round($net / (1 + ($taxRate / 100)), 4) : $net;
                        $taxAmt = round($net - $taxable, 4);
                        $totalAmt = $net;
                    } else {
                        $taxable = $net;
                        $taxAmt = $taxable * ($taxRate / 100);
                        $totalAmt = $taxable + $taxAmt;
                    }

                    $invoiceItems[] = [
                        'sales_order_item_id'          => $soItem?->id,
                        'material_requirement_item_id' => $doItem->material_requirement_item_id,
                        'product_id'                   => $doItem->product_id,
                        'product_name'                 => $doItem->product?->name ?? 'Item',
                        'sku'                          => $doItem->product?->sku ?? null,
                        'item_name'                    => $doItem->product?->name ?? 'Item',
                        'description'                  => null,
                        'quantity'                     => $unbilledQty,
                        'max_quantity'                 => $unbilledQty,
                        'unit_price'                   => $unitPrice,
                        'discount'                     => $discount,
                        'tax_rate'                     => $taxRate,
                        'tax_amount'                   => $taxAmt,
                        'subtotal'                     => $subtotal,
                        'total_amount'                 => $totalAmt,
                        'warehouse_id'                 => $doItem->warehouse_id ?? null,
                        'warehouse_name'               => $doItem->warehouse?->name ?? null,
                    ];
                }
            }
        } elseif ($mode === 'sales_order' && $salesOrder) {
            foreach ($salesOrder->items as $soItem) {
                $orderedQty = floatval($soItem->quantity);

                $alreadyInvoiced = InvoiceItem::whereHas('invoice', fn($q) => $q->where('sales_order_id', $salesOrder->id)->where('status', '!=', 'Cancelled'))
                    ->where('sales_order_item_id', $soItem->id)
                    ->sum('quantity');

                $alreadyReturned = \App\Domains\Sales\Models\SalesReturnItem::whereHas('salesReturn', fn($q) => $q->where('sales_order_id', $salesOrder->id)->where('status', 'Completed')->whereNull('invoice_id'))
                    ->where('product_id', $soItem->product_id)
                    ->sum('quantity');

                $unbilledQty = max(0, $orderedQty - $alreadyInvoiced - $alreadyReturned);
                if ($unbilledQty <= 0.0001) {
                    continue; // Skip items already fully invoiced or returned
                }

                $unitPrice = round(convert_from_base(floatval($soItem->unit_price)), 2);
                $taxRate = floatval($soItem->tax_rate);
                $discount = round(convert_from_base(floatval($soItem->discount)), 2);

                $subtotal = $unbilledQty * $unitPrice;
                $discountAmt = $discount;
                $net = max(0, $subtotal - $discountAmt);

                if ($taxMode === 'inclusive') {
                    $taxable = ($taxRate > 0) ? round($net / (1 + ($taxRate / 100)), 4) : $net;
                    $taxAmt = round($net - $taxable, 4);
                    $totalAmt = $net;
                } else {
                    $taxable = $net;
                    $taxAmt = $taxable * ($taxRate / 100);
                    $totalAmt = $taxable + $taxAmt;
                }

                $invoiceItems[] = [
                    'sales_order_item_id'          => $soItem->id,
                    'material_requirement_item_id' => null,
                    'product_id'                   => $soItem->product_id,
                    'product_name'                 => $soItem->product?->name ?? 'Item',
                    'sku'                          => $soItem->product?->sku ?? null,
                    'item_name'                    => $soItem->product?->name ?? 'Item',
                    'description'                  => $soItem->description,
                    'quantity'                     => $unbilledQty,
                    'max_quantity'                 => $unbilledQty,
                    'unit_price'                   => $unitPrice,
                    'discount'                     => $discount,
                    'tax_rate'                     => $taxRate,
                    'tax_amount'                   => $taxAmt,
                    'subtotal'                     => $subtotal,
                    'total_amount'                 => $totalAmt,
                    'warehouse_id'                 => $soItem->warehouse_id ?? null,
                    'warehouse_name'               => $soItem->warehouse?->name ?? null,
                ];
            }
        }

        $paymentTerms = PaymentTerm::query()->where('is_active', true)->orderBy('due_days')->get();

        return view('modules.sales.invoices.create', compact(
            'mode', 'salesOrders', 'dispatchOrders', 'customers', 'products', 'warehouses', 'paymentTerms',
            'salesOrder', 'dispatchOrder', 'nextInvoiceNumber', 'advanceAllocations', 'invoiceItems', 'customerId', 'invoicingPolicy', 'taxMode'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $companyId = company_id() ?? auth()->user()?->company_id;
        $branchId = branch_id() ?? auth()->user()?->branch_id;
        if ($request->filled('sales_order_id')) {
            $so = SalesOrder::find($request->input('sales_order_id'));
            if ($so) {
                $tenantId = $so->tenant_id ?? $tenantId;
                $companyId = $so->company_id ?? $companyId;
                $branchId = $so->branch_id ?? $branchId;
            }
        }

        $invoiceNumberRule = Rule::unique('invoices', 'invoice_number')->where(function ($query) use ($tenantId, $companyId, $branchId) {
            $query->where('tenant_id', $tenantId);
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            }
            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }
        });

        if (!empty($request->invoice_number) && Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('invoice_number', $request->invoice_number)->exists()) {
            $latest = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->latest('id')->first();
            $nextSeq = $latest ? intval(str_replace('INV-', '', $latest->invoice_number)) + 1 : 1;
            $newInvNum = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            while (Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('invoice_number', $newInvNum)->exists()) {
                $nextSeq++;
                $newInvNum = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            }
            $request->merge(['invoice_number' => $newInvNum]);
        }

        $validated = $request->validate([
            'sales_order_id'          => ['nullable', 'exists:sales_orders,id'],
            'customer_id'             => ['nullable', 'exists:customers,id'],
            'material_requirement_id' => ['nullable', 'exists:material_requirements,id'],
            'invoice_number'          => ['required', 'string', 'max:255', $invoiceNumberRule],
            'invoice_date'            => ['required', 'date'],
            'due_date'                => ['required', 'date', 'after_or_equal:invoice_date'],
            'payment_terms'           => ['nullable', 'string', 'max:255'],
            'tax_mode'                => ['nullable', 'string', 'in:exclusive,inclusive'],
            'gst_type'                => ['nullable', 'string', 'in:cgst_sgst,igst'],
            'freight_terms'           => ['nullable', 'string', 'in:To Pay,To Be Billed,Prepaid,Customer Pickup'],
            'freight_amount'          => ['nullable', 'numeric', 'min:0'],
            'freight_tax_rate'        => ['nullable', 'string'],
            'billing_address'         => ['nullable', 'string'],
            'shipping_address'        => ['nullable', 'string'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id'          => ['nullable', 'exists:sales_order_items,id'],
            'items.*.material_requirement_item_id' => ['nullable', 'integer'],
            'items.*.product_id'                   => ['required', 'integer', 'exists:products,id'],
            'items.*.warehouse_id'                 => ['nullable', 'integer', 'exists:warehouses,id'],
            'items.*.item_name'                    => ['required', 'string', 'max:255'],
            'items.*.description'                  => ['nullable', 'string'],
            'items.*.quantity'                     => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'                   => ['required', 'numeric', 'min:0'],
            'items.*.discount'                     => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate'                     => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.*.product_id.required' => 'Please select a valid Product for each line item.',
            'items.*.product_id.exists'   => 'The selected product does not exist in inventory.',
        ]);

        if (!empty($validated['sales_order_id'])) {
            $so = SalesOrder::with(['items', 'invoices' => fn($q) => $q->where('status', '!=', 'Cancelled')->with('items')])->find($validated['sales_order_id']);
            if ($so) {
                foreach ($validated['items'] as $item) {
                    $soItemId = $item['sales_order_item_id'] ?? null;
                    if ($soItemId) {
                        $soItem = $so->items->firstWhere('id', $soItemId);
                        if ($soItem) {
                            $alreadyInvoiced = $so->invoices->flatMap->items->where('sales_order_item_id', $soItemId)->sum('quantity');
                            $maxAllowed = max(0, (float)$soItem->quantity - $alreadyInvoiced);
                            if (floatval($item['quantity']) > $maxAllowed + 0.0001) {
                                return redirect()->back()->withInput()->with('error', "Cannot invoice {$item['quantity']} units for '{$item['item_name']}'. Maximum remaining quantity allowed is {$maxAllowed}.");
                            }
                        }
                    }
                }
            }
        }

        $salesOrder = !empty($validated['sales_order_id']) ? SalesOrder::find($validated['sales_order_id']) : null;
        $customerId = $validated['customer_id'] ?? $salesOrder?->customer_id;

        if (!$customerId && !empty($validated['material_requirement_id'])) {
            $mr = MaterialRequirement::with('salesOrder')->find($validated['material_requirement_id']);
            $customerId = $mr?->salesOrder?->customer_id;
        }

        if (!$customerId) {
            return redirect()->back()->withInput()->with('error', 'Customer selection is required.');
        }

        $customerObj = \App\Domains\CRM\Models\Customer::find($customerId);

        $tenantId = $salesOrder?->tenant_id ?? (tenant_id() ?? auth()->user()?->tenant_id ?? 1);
        $branchId = branch_id() ?? $salesOrder?->branch_id ?? auth()->user()?->branch_id;
        $companyId = company_id() ?? $salesOrder?->company_id ?? auth()->user()?->company_id;

        $taxMode      = $request->input('tax_mode') ?: ($salesOrder?->effective_tax_mode ?? 'exclusive');
        $taxType      = $request->input('tax_type', 'item_wise_tax');
        $discountType = $request->input('discount_type', 'without_discount');
        $gstType      = $request->input('gst_type', 'cgst_sgst');

        $freightTerms  = $validated['freight_terms'] ?? $salesOrder?->freight_terms ?? 'To Pay';
        $freightAmount = convert_to_base(floatval($validated['freight_amount'] ?? 0));

        $invoice = DB::transaction(function () use ($validated, $salesOrder, $customerId, $customerObj, $tenantId, $branchId, $companyId, $taxMode, $taxType, $discountType, $gstType, $freightTerms, $freightAmount, $request) {
            $subtotal = 0;
            $taxAmount = 0;
            $discountAmount = 0;

            foreach ($validated['items'] as $item) {
                $qty   = floatval($item['quantity']);
                $price = convert_to_base(floatval($item['unit_price']));
                $disc  = ($discountType === 'item_wise') ? convert_to_base(floatval($item['discount'] ?? 0)) : 0;
                $taxR  = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;

                $lineSubtotal = $qty * $price;
                $lineNet      = max(0, $lineSubtotal - $disc);

                if ($taxMode === 'inclusive') {
                    $lineBase = ($taxR > 0 && $taxType === 'item_wise_tax') ? round($lineNet / (1 + ($taxR / 100)), 4) : $lineNet;
                    $lineTax  = ($taxType === 'item_wise_tax') ? ($lineNet - $lineBase) : 0;
                } else {
                    $lineBase = $lineNet;
                    $lineTax  = ($taxType === 'item_wise_tax') ? ($lineBase * ($taxR / 100)) : 0;
                }

                $subtotal       += $lineSubtotal;
                $discountAmount += $disc;
                $taxAmount      += $lineTax;
            }

            if ($discountType === 'order_wise') {
                $discountAmount = convert_to_base(floatval($request->input('discount_amount', 0)));
            } elseif ($discountType === 'without_discount') {
                $discountAmount = 0;
            }

            if ($taxType === 'order_wise_tax') {
                $orderTaxPercent = floatval($request->input('order_tax_percent', 0));
                $taxableBase     = max(0, $subtotal - $discountAmount);
                if ($taxMode === 'inclusive') {
                    $taxAmount = ($orderTaxPercent > 0) ? ($taxableBase - ($taxableBase / (1 + ($orderTaxPercent / 100)))) : 0;
                } else {
                    $taxAmount = $taxableBase * ($orderTaxPercent / 100);
                }
            } elseif ($taxType === 'without_tax') {
                $taxAmount = 0;
            }

            $taxAmount = round($taxAmount, 4);

            $freightTaxRateOpt = $request->input('freight_tax_rate', 'highest');
            $freightTaxRatePercent = 18;
            if ($freightTaxRateOpt === 'highest') {
                if ($taxType === 'order_wise_tax') {
                    $freightTaxRatePercent = floatval($request->input('order_tax_percent', 18));
                } else {
                    $maxTax = 0;
                    foreach ($validated['items'] as $it) {
                        $tr = floatval($it['tax_rate'] ?? 0);
                        if ($tr > $maxTax) $maxTax = $tr;
                    }
                    $freightTaxRatePercent = ($maxTax > 0) ? $maxTax : 18;
                }
            } else {
                $freightTaxRatePercent = floatval($freightTaxRateOpt);
            }

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $freightTax = ($effectiveFreight > 0 && $taxType !== 'without_tax') ? round($effectiveFreight * ($freightTaxRatePercent / 100), 4) : 0;
            $totalTaxAmount = $taxAmount + $freightTax;

            $cgstAmount = 0;
            $sgstAmount = 0;
            $igstAmount = 0;

            if ($totalTaxAmount > 0) {
                if ($gstType === 'igst') {
                    $igstAmount = $totalTaxAmount;
                } else {
                    $cgstAmount = round($totalTaxAmount / 2, 4);
                    $sgstAmount = round($totalTaxAmount - $cgstAmount, 4);
                }
            }

            $adjustment = convert_to_base(floatval($request->input('adjustment', 0)));
            if ($taxMode === 'inclusive') {
                $totalAmount = max(0, $subtotal - $discountAmount) + $effectiveFreight + $adjustment;
            } else {
                $totalAmount = max(0, $subtotal - $discountAmount + $totalTaxAmount + $effectiveFreight + $adjustment);
            }

            $invoice = Invoice::create([
                'tenant_id'               => $tenantId,
                'company_id'              => $companyId,
                'branch_id'               => $branchId,
                'sales_order_id'          => $salesOrder?->id,
                'customer_id'             => $customerId,
                'billing_address'         => !empty($validated['billing_address']) ? $validated['billing_address'] : ($salesOrder?->billing_address ?: $customerObj?->billing_address),
                'shipping_address'        => !empty($validated['shipping_address']) ? $validated['shipping_address'] : ($salesOrder?->shipping_address ?: ($customerObj?->shipping_address ?: ($salesOrder?->billing_address ?: $customerObj?->billing_address))),
                'material_requirement_id' => $validated['material_requirement_id'] ?? null,
                'invoice_number'          => $validated['invoice_number'],
                'invoice_date'            => $validated['invoice_date'],
                'due_date'                => $validated['due_date'],
                'payment_terms'           => $validated['payment_terms'] ?? ($salesOrder?->payment_terms ?? null),
                'status'                  => 'Draft',
                'discount_type'           => $discountType,
                'tax_type'                => $taxType,
                'tax_mode'                => $taxMode,
                'order_tax_rate'          => floatval($request->input('order_tax_percent', $request->input('order_tax_rate', 0))),
                'adjustment'              => $adjustment,
                'subtotal'                => $subtotal,
                'tax_amount'              => $totalTaxAmount,
                'gst_type'                => $gstType,
                'cgst_amount'             => $cgstAmount,
                'sgst_amount'             => $sgstAmount,
                'igst_amount'             => $igstAmount,
                'discount_amount'         => $discountAmount,
                'freight_terms'           => $freightTerms,
                'freight_amount'          => $freightAmount,
                'freight_tax_rate'        => $freightTaxRateOpt,
                'total_amount'            => $totalAmount,
                'amount_paid'             => 0,
                'balance_due'             => $totalAmount,
                'notes'                   => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = floatval($item['quantity']);
                $price = convert_to_base(floatval($item['unit_price']));
                $disc = convert_to_base(floatval($item['discount'] ?? 0));
                $taxR = floatval($item['tax_rate'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineNet = max(0, $lineSubtotal - $disc);

                if ($taxMode === 'inclusive') {
                    $lineBase = ($taxR > 0 && $taxType === 'item_wise_tax') ? round($lineNet / (1 + ($taxR / 100)), 4) : $lineNet;
                    $lineTax = ($taxType === 'item_wise_tax') ? ($lineNet - $lineBase) : 0;
                    $lineTotal = $lineNet;
                } else {
                    $lineBase = $lineNet;
                    $lineTax = ($taxType === 'item_wise_tax') ? ($lineBase * ($taxR / 100)) : 0;
                    $lineTotal = $lineBase + $lineTax;
                }

                $lineCgstPercent = 0;
                $lineSgstPercent = 0;
                $lineIgstPercent = 0;
                $lineCgstAmt = 0;
                $lineSgstAmt = 0;
                $lineIgstAmt = 0;

                if ($lineTax > 0 || $taxR > 0) {
                    if ($gstType === 'igst') {
                        $lineIgstPercent = $taxR;
                        $lineIgstAmt = $lineTax;
                    } else {
                        $lineCgstPercent = round($taxR / 2, 2);
                        $lineSgstPercent = round($taxR / 2, 2);
                        $lineCgstAmt = round($lineTax / 2, 4);
                        $lineSgstAmt = round($lineTax - $lineCgstAmt, 4);
                    }
                }

                InvoiceItem::create([
                    'invoice_id'                   => $invoice->id,
                    'sales_order_item_id'          => $item['sales_order_item_id'] ?? null,
                    'material_requirement_item_id' => $item['material_requirement_item_id'] ?? null,
                    'product_id'                   => $item['product_id'] ?? null,
                    'warehouse_id'                 => $item['warehouse_id'] ?? null,
                    'item_name'                    => $item['item_name'],
                    'description'                  => $item['description'] ?? null,
                    'quantity'                     => $qty,
                    'unit_price'                   => $price,
                    'discount'                     => $disc,
                    'tax_rate'                     => $taxR,
                    'tax_amount'                   => $lineTax,
                    'cgst_percent'                 => $lineCgstPercent,
                    'sgst_percent'                 => $lineSgstPercent,
                    'igst_percent'                 => $lineIgstPercent,
                    'cgst_amount'                  => $lineCgstAmt,
                    'sgst_amount'                  => $lineSgstAmt,
                    'igst_amount'                  => $lineIgstAmt,
                    'subtotal'                     => $lineSubtotal,
                    'total_amount'                 => $lineTotal,
                ]);
            }

            $unallocatedAdvances = $salesOrder ? PaymentAllocation::where('sales_order_id', $salesOrder->id)
                ->whereNull('invoice_id')
                ->get() : collect();

            $remainingBalance = $invoice->balance_due;
            foreach ($unallocatedAdvances as $allocation) {
                if ($remainingBalance <= 0) break;

                $originalAllocated = (float) $allocation->allocated_amount;
                $applyAmount = min($originalAllocated, $remainingBalance);

                if ($applyAmount < $originalAllocated) {
                    $remainder = $originalAllocated - $applyAmount;
                    PaymentAllocation::create([
                        'tenant_id'           => $allocation->tenant_id,
                        'company_id'          => $allocation->company_id,
                        'branch_id'           => $allocation->branch_id,
                        'customer_payment_id' => $allocation->customer_payment_id,
                        'sales_order_id'      => $allocation->sales_order_id,
                        'invoice_id'          => null,
                        'allocated_amount'    => $remainder,
                    ]);
                }

                $allocation->update([
                    'invoice_id'       => $invoice->id,
                    'allocated_amount' => $applyAmount,
                ]);

                $invoice->amount_paid += $applyAmount;
                $invoice->balance_due -= $applyAmount;
                $remainingBalance -= $applyAmount;
            }

            return $invoice;
        });

        if ($request->filled('dispatch_order_id')) {
            $do = \App\Domains\Sales\Models\DispatchOrder::find($request->input('dispatch_order_id'));
            if ($do && empty($do->invoice_id)) {
                $do->update(['invoice_id' => $invoice->id]);
            }
        }

        if ($salesOrder) {
            foreach ($salesOrder->items as $soItem) {
                $soItem->refreshStatus();
            }
            $salesOrder->refreshFulfillmentStatus();
        }

        event(new InvoicePosted($invoice));

        \App\Domains\Platform\Services\NotificationRuleService::trigger('sales.invoice.created', [
            'doc_no' => $invoice->invoice_number,
            'customer_name' => $invoice->customer?->name ?? 'Customer',
            'amount' => number_format((float)$invoice->total_amount, 2),
            'due_date' => $invoice->due_date ? (is_string($invoice->due_date) ? $invoice->due_date : $invoice->due_date->format('Y-m-d')) : 'N/A',
            'created_by' => auth()->user()?->name ?? 'System',
        ], route('sales.invoices.show', $invoice->id), $invoice);

        return redirect()->route('sales.invoices.show', $invoice->id)->with('success', "Invoice {$invoice->invoice_number} created successfully.");
    }

    public function show(int $id): View
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404);
        $this->authorize('view', $invoice);

        $invoice->loadMissing(['customer', 'salesOrder.customer', 'materialRequirement', 'items.product', 'allocations.payment']);

        $adjustedAmount = $invoice->allocations->sum('allocated_amount');
        $balanceDue     = $invoice->balance_due;

        $tenantId = require_tenant_id();

        // 1. Unallocated advances tied to this Sales Order
        $availableAdvances = collect();
        if ($invoice->sales_order_id) {
            $availableAdvances = PaymentAllocation::with('payment')
                ->where('sales_order_id', $invoice->sales_order_id)
                ->whereNull('invoice_id')
                ->where('allocated_amount', '>', 0)
                ->get();
        }

        // 2. Direct unallocated customer payments (not yet assigned to any SO or invoice)
        $customerId = $invoice->customer_id ?? $invoice->salesOrder?->customer_id;
        $unallocatedPayments = collect();
        if ($customerId) {
            $custPayments = CustomerPayment::where('customer_id', $customerId)
                ->where('status', 'Posted')
                ->with('allocations')
                ->get();
            foreach ($custPayments as $cp) {
                $alreadyAlloc = (float)$cp->allocations->sum('allocated_amount');
                $unallocBal = max(0, (float)$cp->amount - $alreadyAlloc);
                if ($unallocBal > 0.009) {
                    $unallocatedPayments->push((object)[
                        'customer_payment_id' => $cp->id,
                        'payment'             => $cp,
                        'allocated_amount'    => $unallocBal,
                    ]);
                }
            }
        }

        $transporters = \App\Domains\Platform\Models\Transporter::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $count = \App\Domains\Platform\Models\Transporter::where('tenant_id', $tenantId)->count() + 1;
        $autoTransporterCode = 'TRP-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Check if any goods have been dispatched for this invoice or its sales order
        $hasDispatched = DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($tenantId, $invoice) {
            $q->where('tenant_id', $tenantId)
              ->where('status', '!=', 'Cancelled')
              ->where(function ($sub) use ($invoice) {
                  $sub->where('invoice_id', $invoice->id);
                  if ($invoice->sales_order_id) {
                      $sub->orWhere('sales_order_id', $invoice->sales_order_id);
                  }
                  if ($invoice->material_requirement_id) {
                      $sub->orWhere('material_requirement_id', $invoice->material_requirement_id);
                  }
              });
        })
        ->where(function ($q) {
            $q->where('quantity_dispatched', '>', 0)
              ->orWhere('quantity_ordered', '>', 0);
        })
        ->exists();

        // Linked Financial Credit Notes
        $linkedCreditNotes = Journal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'invoice')
            ->where('reference_id', $invoice->id)
            ->where('voucher_type', VoucherType::CREDIT_NOTE)
            ->get();

        // Active Cash and Bank accounts for potential refunds
        $cashBankAccounts = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_cash_or_bank', true)
                  ->orWhere('name', 'like', '%Bank%')
                  ->orWhere('name', 'like', '%Cash%');
            })
            ->orderBy('name')
            ->get();

        return view('modules.sales.invoices.show', compact(
            'invoice', 'adjustedAmount', 'balanceDue', 'transporters', 'autoTransporterCode',
            'hasDispatched', 'linkedCreditNotes', 'cashBankAccounts',
            'availableAdvances', 'unallocatedPayments'
        ));
    }

    /**
     * Issue a Financial Credit Note directly from the Invoice (for un-dispatched orders or financial adjustments)
     */
    public function storeCreditNote(Request $request, int $invoice): RedirectResponse
    {
        $inv = $this->invoiceRepo->find($invoice);
        if (!$inv) abort(404);
        $this->authorize('update', $inv);

        if ($inv->status === 'Cancelled') {
            return back()->withErrors(['error' => 'This invoice is already cancelled.']);
        }

        $tenantId = $inv->tenant_id ?? require_tenant_id();

        // Enforce dispatch guard
        $hasDispatched = DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($tenantId, $inv) {
            $q->where('tenant_id', $tenantId)
              ->where('status', '!=', 'Cancelled')
              ->where(function ($sub) use ($inv) {
                  $sub->where('invoice_id', $inv->id);
                  if ($inv->sales_order_id) {
                      $sub->orWhere('sales_order_id', $inv->sales_order_id);
                  }
                  if ($inv->material_requirement_id) {
                      $sub->orWhere('material_requirement_id', $inv->material_requirement_id);
                  }
              });
        })
        ->where(function ($q) {
            $q->where('quantity_dispatched', '>', 0)
              ->orWhere('quantity_ordered', '>', 0);
        })
        ->exists();

        if ($hasDispatched) {
            return back()->withErrors(['error' => 'Goods for this invoice have already been dispatched. Please create a Sales Return instead of a direct financial credit note.']);
        }

        $validated = $request->validate([
            'reason'                 => ['required', 'string', 'max:255'],
            'credit_note_date'       => ['required', 'date'],
            'refund_action'          => ['required', 'string', 'in:credit_balance,bank_refund'],
            'refund_bank_account_id' => ['nullable', 'required_if:refund_action,bank_refund', 'integer'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
        ]);

        $journal = DB::transaction(function () use ($inv, $tenantId, $validated) {
            $accountsReceivable = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '1100')->first()
                ?? ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', 'like', '%Accounts Receivable%')->first();
            $salesReturnAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '4030')->first()
                ?? ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', 'like', '%Sales Return%')->first()
                ?? ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '4010')->first();
            $cgstAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '2110')->first();
            $sgstAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '2120')->first();
            $igstAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '2130')->first()
                ?? ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', 'like', '%Output IGST%')->first();
            $freightAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '4900')->first()
                ?? ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('name', 'like', '%Freight%')->first();
            $roundOffAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', '5730')->first();

            $taxableNet = max(0, (float)$inv->subtotal - (float)$inv->discount_amount);
            $cgst = (float)$inv->cgst_amount;
            $sgst = (float)$inv->sgst_amount;
            $igst = (float)$inv->igst_amount;
            $freight = ($inv->freight_terms === 'To Be Billed') ? (float)$inv->freight_amount : 0;
            $totalAmount = (float)$inv->total_amount;

            $lines = [];
            if ($taxableNet > 0 && $salesReturnAccount) {
                $lines[] = [
                    'chart_of_account_id' => $salesReturnAccount->id,
                    'debit'               => round($taxableNet, 2),
                    'credit'              => 0,
                    'description'         => "Sales Return / Credit Note - {$inv->invoice_number}",
                ];
            }
            if ($cgst > 0 && $cgstAccount) {
                $lines[] = [
                    'chart_of_account_id' => $cgstAccount->id,
                    'debit'               => round($cgst, 2),
                    'credit'              => 0,
                    'description'         => "CGST Output Reversal - {$inv->invoice_number}",
                ];
            }
            if ($sgst > 0 && $sgstAccount) {
                $lines[] = [
                    'chart_of_account_id' => $sgstAccount->id,
                    'debit'               => round($sgst, 2),
                    'credit'              => 0,
                    'description'         => "SGST Output Reversal - {$inv->invoice_number}",
                ];
            }
            if ($igst > 0 && $igstAccount) {
                $lines[] = [
                    'chart_of_account_id' => $igstAccount->id,
                    'debit'               => round($igst, 2),
                    'credit'              => 0,
                    'description'         => "IGST Output Reversal - {$inv->invoice_number}",
                ];
            }
            if ($freight > 0 && $freightAccount) {
                $lines[] = [
                    'chart_of_account_id' => $freightAccount->id,
                    'debit'               => round($freight, 2),
                    'credit'              => 0,
                    'description'         => "Freight Charges Reversal - {$inv->invoice_number}",
                ];
            }
            if ($accountsReceivable) {
                $lines[] = [
                    'chart_of_account_id' => $accountsReceivable->id,
                    'debit'               => 0,
                    'credit'              => round($totalAmount, 2),
                    'description'         => "Credit Note Total Value - {$inv->invoice_number}",
                    'party_type'          => JournalEntry::PARTY_CUSTOMER,
                    'party_id'            => $inv->customer_id ?: $inv->salesOrder?->customer_id,
                ];
            }

            // Penny balance check
            $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);
            $diff = round($totalDebit - $totalCredit, 2);
            if (abs($diff) > 0 && abs($diff) <= 0.05 && $roundOffAccount) {
                $lines[] = [
                    'chart_of_account_id' => $roundOffAccount->id,
                    'debit'               => $diff < 0 ? abs($diff) : 0,
                    'credit'              => $diff > 0 ? $diff : 0,
                    'description'         => "Round Off - Credit Note {$inv->invoice_number}",
                ];
            }

            $voucherService = app(VoucherService::class);
            $journal = $voucherService->post(VoucherType::CREDIT_NOTE, $lines, [
                'tenant_id'      => $tenantId,
                'company_id'     => $inv->company_id,
                'branch_id'      => $inv->branch_id,
                'journal_date'   => $validated['credit_note_date'],
                'reference_type' => 'invoice',
                'reference_id'   => $inv->id,
                'memo'           => "Credit Note for Invoice {$inv->invoice_number}: {$validated['reason']}",
                'party_type'     => 'customer',
                'party_id'       => $inv->customer_id ?: $inv->salesOrder?->customer_id,
                'party_name'     => $inv->customer?->name ?: $inv->salesOrder?->customer?->name,
                'reference_no'   => $inv->invoice_number,
                'posted_by'      => auth()->id(),
            ]);

            // Handle payments if any
            if ((float)$inv->amount_paid > 0) {
                if ($validated['refund_action'] === 'bank_refund' && !empty($validated['refund_bank_account_id'])) {
                    $bankAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($validated['refund_bank_account_id']);
                    if ($bankAccount && $accountsReceivable) {
                        $refundLines = [
                            [
                                'chart_of_account_id' => $accountsReceivable->id,
                                'debit'               => round((float)$inv->amount_paid, 2),
                                'credit'              => 0,
                                'description'         => "Customer Refund for Invoice {$inv->invoice_number}",
                                'party_type'          => JournalEntry::PARTY_CUSTOMER,
                                'party_id'            => $inv->customer_id ?: $inv->salesOrder?->customer_id,
                            ],
                            [
                                'chart_of_account_id' => $bankAccount->id,
                                'debit'               => 0,
                                'credit'              => round((float)$inv->amount_paid, 2),
                                'description'         => "Bank Payout - Customer Refund {$inv->invoice_number}",
                            ],
                        ];
                        $voucherService->post(VoucherType::PAYMENT, $refundLines, [
                            'tenant_id'      => $tenantId,
                            'company_id'     => $inv->company_id,
                            'branch_id'      => $inv->branch_id,
                            'journal_date'   => $validated['credit_note_date'],
                            'reference_type' => 'invoice_refund',
                            'reference_id'   => $inv->id,
                            'memo'           => "Customer Refund for {$inv->invoice_number} via {$bankAccount->name}",
                            'party_type'     => 'customer',
                            'party_id'       => $inv->customer_id ?: $inv->salesOrder?->customer_id,
                            'party_name'     => $inv->customer?->name ?: $inv->salesOrder?->customer?->name,
                            'reference_no'   => $inv->invoice_number,
                            'posted_by'      => auth()->id(),
                        ]);
                    }
                } else {
                    // Retain as Customer Advance Credit: unallocate payment allocation from this invoice
                    PaymentAllocation::where('invoice_id', $inv->id)
                        ->update(['invoice_id' => null]);
                }
            }

            // Update Invoice
            $inv->status = 'Cancelled';
            $inv->balance_due = 0;
            $cancellationNote = "Cancelled via Financial Credit Note #{$journal->journal_number} on " . date('Y-m-d') . ". Reason: " . $validated['reason'];
            if (!empty($validated['notes'])) {
                $cancellationNote .= " | Notes: " . $validated['notes'];
            }
            $inv->notes = $inv->notes ? ($inv->notes . "\n" . $cancellationNote) : $cancellationNote;
            $inv->save();

            return $journal;
        });

        if ($inv->sales_order_id && $inv->salesOrder) {
            foreach ($inv->salesOrder->items as $soItem) {
                $soItem->refreshStatus();
            }
            $inv->salesOrder->refreshFulfillmentStatus();
        }

        return redirect()->route('sales.invoices.show', $inv->id)
            ->with('success', "Financial Credit Note {$journal->journal_number} created successfully. Invoice {$inv->invoice_number} has been cancelled without altering warehouse inventory.");
    }


    public function post(int $invoice): RedirectResponse
    {
        $inv = $this->invoiceRepo->find($invoice);
        if (!$inv) abort(404);
        $this->authorize('update', $inv);

        if ($inv->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only Draft invoices can be posted.']);
        }

        $inv->status = 'Posted';
        $inv->save();

        event(new InvoicePosted($inv));

        return redirect()->route('sales.invoices.show', $inv->id)->with('success', "Invoice {$inv->invoice_number} posted successfully.");
    }

    public function send(int $invoice): RedirectResponse
    {
        $inv = $this->invoiceRepo->find($invoice);
        if (!$inv) abort(404);
        $this->authorize('send', $inv);

        if (!in_array($inv->status, ['Draft', 'Sent', 'Posted'])) {
            return back()->withErrors(['status' => 'Only Draft or Posted invoices can be marked as Sent.']);
        }

        $inv->status = 'Sent';
        $inv->save();

        return redirect()->route('sales.invoices.show', $inv->id)->with('success', "Invoice {$inv->invoice_number} marked as Sent.");
    }

    public function pay(int $invoice): RedirectResponse
    {
        $inv = $this->invoiceRepo->find($invoice);
        if (!$inv) abort(404);
        $this->authorize('update', $inv);

        if ($inv->status === 'Paid') {
            return back()->withErrors(['status' => 'Invoice is already fully paid.']);
        }

        $inv->status      = 'Paid';
        $inv->amount_paid = $inv->total_amount;
        $inv->balance_due = 0;
        $inv->save();

        return redirect()->route('sales.invoices.show', $inv->id)->with('success', "Invoice {$inv->invoice_number} marked as Paid.");
    }

    public function viewPdf(int $id)
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404, 'Invoice not found.');
        $this->authorize('view', $invoice);

        $adjustedAmount = $invoice->allocations->sum('allocated_amount');
        $balanceDue     = $invoice->balance_due;

        $pdf = Pdf::loadView('modules.sales.invoices.pdf', compact('invoice', 'adjustedAmount', 'balanceDue'));
        return $pdf->stream("Tax_Invoice_{$invoice->invoice_number}.pdf");
    }

    public function downloadPdf(int $id)
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404, 'Invoice not found.');
        $this->authorize('view', $invoice);

        $adjustedAmount = $invoice->allocations->sum('allocated_amount');
        $balanceDue     = $invoice->balance_due;

        $pdf = Pdf::loadView('modules.sales.invoices.pdf', compact('invoice', 'adjustedAmount', 'balanceDue'));
        return $pdf->download("Tax_Invoice_{$invoice->invoice_number}.pdf");
    }

    public function sendEmail(Request $request, int $id)
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404, 'Invoice not found.');
        $this->authorize('view', $invoice);

        $request->validate([
            'to_email'   => 'required|email',
            'subject'    => 'required|string|max:255',
            'body_html'  => 'required|string',
            'account_id' => 'nullable|exists:email_configurations,id',
            'custom_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        try {
            /** @var \App\Services\EmailService $emailService */
            $emailService = app(\App\Services\EmailService::class);
            $msgRecord = $emailService->sendInvoiceEmail($invoice, $request->all(), $request->file('custom_pdf'));

            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_number} sent successfully to {$request->input('to_email')} with PDF attached!",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send Invoice Email: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function sendWhatsApp(Request $request, int $id)
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404, 'Invoice not found.');
        $this->authorize('view', $invoice);

        $request->validate([
            'phone'      => 'required|string',
            'caption'    => 'nullable|string',
            'custom_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $phone = $request->input('phone');

        try {
            /** @var \App\Services\WhatsAppService $waService */
            $waService = app(\App\Services\WhatsAppService::class);
            $result = $waService->sendInvoice(
                invoice: $invoice,
                mobile: $phone,
                customCaption: $request->input('caption'),
                customPdfFile: $request->file('custom_pdf')
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send WhatsApp message.',
                    'status'  => $result['status'] ?? 'error',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reconcile / Apply an outstanding Advance Payment to this Invoice (Odoo-style post-invoice reconciliation)
     */
    public function applyAdvance(Request $request, int $invoiceId): RedirectResponse
    {
        $invoice = $this->invoiceRepo->find($invoiceId);
        if (!$invoice) abort(404);
        $this->authorize('update', $invoice);

        if ($invoice->status === 'Cancelled') {
            return back()->withErrors(['error' => 'Cannot apply payment to a cancelled invoice.']);
        }

        if ((float)$invoice->balance_due <= 0.009) {
            return back()->with('info', 'This invoice is already fully paid.');
        }

        $validated = $request->validate([
            'source_type'           => ['required', 'in:allocation,payment'],
            'payment_allocation_id' => ['nullable', 'required_if:source_type,allocation', 'integer'],
            'customer_payment_id'   => ['nullable', 'required_if:source_type,payment', 'integer'],
            'amount'                => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $tenantId = $invoice->tenant_id ?? require_tenant_id();

        $appliedAmount = DB::transaction(function () use ($invoice, $validated, $tenantId) {
            $balDue = (float)$invoice->balance_due;

            if ($validated['source_type'] === 'allocation') {
                $allocation = PaymentAllocation::where('id', $validated['payment_allocation_id'])
                    ->whereNull('invoice_id')
                    ->where('allocated_amount', '>', 0)
                    ->firstOrFail();

                $avail = (float)$allocation->allocated_amount;
                $maxPossible = min($avail, $balDue);
                $applyAmount = !empty($validated['amount']) ? min((float)$validated['amount'], $maxPossible) : $maxPossible;

                if ($applyAmount < ($avail - 0.009)) {
                    // Split allocation: remainder stays unallocated on sales order
                    $remainder = round($avail - $applyAmount, 4);
                    $allocation->update(['allocated_amount' => $remainder]);

                    PaymentAllocation::create([
                        'tenant_id'           => $allocation->tenant_id ?? $tenantId,
                        'company_id'          => $allocation->company_id,
                        'branch_id'           => $allocation->branch_id,
                        'customer_payment_id' => $allocation->customer_payment_id,
                        'sales_order_id'      => $allocation->sales_order_id,
                        'invoice_id'          => $invoice->id,
                        'allocated_amount'    => $applyAmount,
                    ]);
                } else {
                    // Full allocation applies to this invoice
                    $allocation->update([
                        'invoice_id'       => $invoice->id,
                        'allocated_amount' => $applyAmount,
                    ]);
                }
            } else {
                // Direct customer payment unallocated balance
                $payment = CustomerPayment::where('id', $validated['customer_payment_id'])->firstOrFail();

                $alreadyAlloc = (float)$payment->allocations()->sum('allocated_amount');
                $avail = max(0, (float)$payment->amount - $alreadyAlloc);
                if ($avail <= 0.009) {
                    throw new \Exception('This payment has no unallocated balance remaining.');
                }

                $maxPossible = min($avail, $balDue);
                $applyAmount = !empty($validated['amount']) ? min((float)$validated['amount'], $maxPossible) : $maxPossible;

                PaymentAllocation::create([
                    'tenant_id'           => $payment->tenant_id ?? $tenantId,
                    'company_id'          => $payment->company_id,
                    'branch_id'           => $payment->branch_id,
                    'customer_payment_id' => $payment->id,
                    'sales_order_id'      => $invoice->sales_order_id,
                    'invoice_id'          => $invoice->id,
                    'allocated_amount'    => $applyAmount,
                ]);
            }

            $invoice->amount_paid = round((float)$invoice->amount_paid + $applyAmount, 2);
            $invoice->balance_due = max(0, round((float)$invoice->total_amount - (float)$invoice->amount_paid, 2));
            $invoice->status      = $invoice->balance_due <= 0.009 ? 'Paid' : 'Partially Paid';
            $invoice->save();

            return $applyAmount;
        });

        return back()->with('success', "Advance of " . format_currency($appliedAmount) . " successfully reconciled and applied to invoice {$invoice->invoice_number}.");
    }

    /**
     * Unlink an applied payment/advance from this invoice (returns back to available advance pool)
     */
    public function removeAdvance(Request $request, int $invoiceId, int $allocationId): RedirectResponse
    {
        $invoice = $this->invoiceRepo->find($invoiceId);
        if (!$invoice) abort(404);
        $this->authorize('update', $invoice);

        $allocation = PaymentAllocation::where('id', $allocationId)
            ->where('invoice_id', $invoice->id)
            ->firstOrFail();

        DB::transaction(function () use ($invoice, $allocation) {
            $unlinkedAmount = (float)$allocation->allocated_amount;

            if ($allocation->sales_order_id) {
                // Revert to Sales Order advance credit
                $allocation->update([
                    'invoice_id' => null,
                ]);
            } else {
                // Delete allocation row so balance returns to general customer payment pool
                $allocation->delete();
            }

            $invoice->amount_paid = max(0, round((float)$invoice->amount_paid - $unlinkedAmount, 2));
            $invoice->balance_due = max(0, round((float)$invoice->total_amount - (float)$invoice->amount_paid, 2));
            $invoice->status      = $invoice->amount_paid <= 0.009 ? 'Posted' : ($invoice->balance_due <= 0.009 ? 'Paid' : 'Partially Paid');
            $invoice->save();
        });

        return back()->with('success', "Payment unlinked successfully. Returned to available advances.");
    }
}

