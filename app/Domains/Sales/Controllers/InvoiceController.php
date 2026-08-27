<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Repositories\InvoiceRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        $customerId = $request->input('customer_id') ?: $request->query('customer_id');
        $requestedMode = $request->input('mode') ?: $request->input('amp;mode') ?: $request->query('mode') ?: $request->query('amp;mode');
        $salesOrderId = $request->input('sales_order_id') ?: $request->input('amp;sales_order_id') ?: $request->query('sales_order_id') ?: $request->query('amp;sales_order_id');
        $dispatchOrderId = $request->input('dispatch_order_id') ?? $request->input('dispatch_id') ?? $request->input('material_requirement_id') ?? $request->input('amp;dispatch_order_id') ?? $request->query('dispatch_order_id');

        $mode = $requestedMode ?: ($customerId ? 'direct' : 'sales_order');

        // Fetch Sales Orders that have unbilled quantities remaining
        $allSalesOrders = SalesOrder::with(['customer', 'items.product', 'items.warehouse', 'invoices' => function($q) {
            $q->where('status', '!=', 'Cancelled')->with('items');
        }])
        ->whereIn('status', ['Confirmed', 'Partially Shipped', 'Shipped', 'Invoiced'])
        ->latest()->get();

        $salesOrders = $allSalesOrders->filter(function ($so) {
            $totalOrdered = $so->items->sum('quantity');
            $totalInvoiced = $so->invoices->flatMap->items->where('sales_order_item_id', '!=', null)->sum('quantity');
            return ($totalOrdered - $totalInvoiced) > 0.0001;
        });

        // Fetch Dispatch Orders that have status Confirmed, Shipped, Dispatched, or Delivered and have unbilled quantities remaining
        $allDispatchOrders = \App\Domains\Sales\Models\DispatchOrder::with(['salesOrder.customer', 'items.product', 'items.warehouse', 'materialRequirement'])
            ->whereIn('status', ['Confirmed', 'Shipped', 'Dispatched', 'Delivered'])
            ->latest()->get();

        $dispatchOrders = $allDispatchOrders->filter(function ($do) {
            if (in_array($do->status, ['Invoiced', 'Fully Invoiced', 'Completed', 'Cancelled'])) {
                return false;
            }

            // Check if an active non-cancelled invoice exists for this dispatch order's material_requirement_id
            if ($do->material_requirement_id) {
                $hasInvoice = Invoice::where('status', '!=', 'Cancelled')
                    ->where('material_requirement_id', $do->material_requirement_id)
                    ->exists();

                if ($hasInvoice) {
                    return false;
                }
            }

            return true;
        });

        $customers  = \App\Domains\CRM\Models\Customer::query()->orderBy('name')->get();
        $products   = \App\Domains\Inventory\Models\Product::where('status', 'active')->orderBy('name')->get();
        $warehouses = Warehouse::query()->orderBy('name')->get();

        $dispatchOrder = null;
        $materialRequirement = null;

        if (!$dispatchOrderId && $salesOrderId) {
            $targetDo = $dispatchOrders->where('sales_order_id', $salesOrderId)->first();
            if ($targetDo) {
                $dispatchOrder = $targetDo;
                $dispatchOrderId = $targetDo->id;
            }
        }

        if ($dispatchOrderId) {
            if (!$dispatchOrder) {
                $dispatchOrder = \App\Domains\Sales\Models\DispatchOrder::with(['items.product', 'items.warehouse', 'salesOrder.customer', 'salesOrder.items', 'materialRequirement'])->find($dispatchOrderId);
            }
            if (!$dispatchOrder) {
                $dispatchOrder = \App\Domains\Sales\Models\DispatchOrder::with(['items.product', 'items.warehouse', 'salesOrder.customer', 'salesOrder.items', 'materialRequirement'])
                    ->where('material_requirement_id', $dispatchOrderId)
                    ->latest()
                    ->first();
            }
            if ($dispatchOrder) {
                $salesOrderId = $dispatchOrder->sales_order_id;
                $materialRequirementId = $dispatchOrder->material_requirement_id;
                $mode = 'dispatch_order';

                if (!$dispatchOrders->contains('id', $dispatchOrder->id)) {
                    $dispatchOrders->push($dispatchOrder);
                }
            }
        }

        if ($requestedMode === 'dispatch' || $requestedMode === 'dispatch_order') {
            $mode = 'dispatch_order';

            if ($salesOrderId) {
                $soDOs = $dispatchOrders->filter(fn($do) => $do->sales_order_id == $salesOrderId)->values();
                if ($soDOs->isNotEmpty()) {
                    $dispatchOrders = $soDOs;
                }

                if (!$dispatchOrder && $dispatchOrders->isNotEmpty()) {
                    $dispatchOrder = $dispatchOrders->first();
                    if ($dispatchOrder) {
                        $dispatchOrderId = $dispatchOrder->id;
                    }
                }
            }
        }

        $salesOrder = $salesOrderId ? SalesOrder::with('items.product', 'items.warehouse', 'customer')->find($salesOrderId) : null;
        if ($salesOrder && !$dispatchOrderId && $mode !== 'dispatch_order') {
            $mode = 'sales_order';
            if (!$salesOrders->contains('id', $salesOrder->id)) {
                $salesOrders->push($salesOrder);
            }
        }

        $latest = Invoice::latest('id')->first();
        $nextSeq = $latest ? intval(str_replace('INV-', '', $latest->invoice_number)) + 1 : 1;
        $nextInvoiceNumber = 'INV-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $advanceAllocations = 0;
        if ($salesOrder) {
            $advanceAllocations = PaymentAllocation::where('sales_order_id', $salesOrder->id)
                ->whereNull('invoice_id')
                ->sum('allocated_amount');
        }

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

                    $unitPrice = $soItem ? floatval($soItem->unit_price) : floatval($doItem->product?->selling_price ?? 0);
                    $taxRate = $soItem ? floatval($soItem->tax_rate) : 0.0;
                    $discount = $soItem ? floatval($soItem->discount) : 0.0;

                    $subtotal = $unbilledQty * $unitPrice;
                    $discountAmt = $discount;
                    $taxable = max(0, $subtotal - $discountAmt);
                    $taxAmt = $taxable * ($taxRate / 100);

                    $invoiceItems[] = [
                        'sales_order_item_id'          => $soItem?->id,
                        'material_requirement_item_id' => $doItem->material_requirement_item_id,
                        'product_id'                   => $doItem->product_id,
                        'product_name'                 => $doItem->product?->name ?? 'Item',
                        'sku'                          => $doItem->product?->sku ?? null,
                        'item_name'                    => $doItem->product?->name ?? 'Item',
                        'description'                  => null,
                        'quantity'                     => $unbilledQty,
                        'unit_price'                   => $unitPrice,
                        'discount'                     => $discount,
                        'tax_rate'                     => $taxRate,
                        'tax_amount'                   => $taxAmt,
                        'subtotal'                     => $subtotal,
                        'total_amount'                 => $taxable + $taxAmt,
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

                $unbilledQty = max(0, $orderedQty - $alreadyInvoiced);
                if ($unbilledQty <= 0.0001) {
                    continue; // Skip items already fully invoiced
                }

                $unitPrice = floatval($soItem->unit_price);
                $taxRate = floatval($soItem->tax_rate);
                $discount = floatval($soItem->discount);

                $subtotal = $unbilledQty * $unitPrice;
                $discountAmt = $discount;
                $taxable = max(0, $subtotal - $discountAmt);
                $taxAmt = $taxable * ($taxRate / 100);

                $invoiceItems[] = [
                    'sales_order_item_id'          => $soItem->id,
                    'material_requirement_item_id' => null,
                    'product_id'                   => $soItem->product_id,
                    'product_name'                 => $soItem->product?->name ?? 'Item',
                    'sku'                          => $soItem->product?->sku ?? null,
                    'item_name'                    => $soItem->product?->name ?? 'Item',
                    'description'                  => $soItem->description,
                    'quantity'                     => $unbilledQty,
                    'unit_price'                   => $unitPrice,
                    'discount'                     => $discount,
                    'tax_rate'                     => $taxRate,
                    'tax_amount'                   => $taxAmt,
                    'subtotal'                     => $subtotal,
                    'total_amount'                 => $taxable + $taxAmt,
                    'warehouse_id'                 => $soItem->warehouse_id ?? null,
                    'warehouse_name'               => $soItem->warehouse?->name ?? null,
                ];
            }
        }

        return view('modules.sales.invoices.create', compact(
            'mode', 'salesOrders', 'dispatchOrders', 'customers', 'products', 'warehouses',
            'salesOrder', 'dispatchOrder', 'nextInvoiceNumber', 'advanceAllocations', 'invoiceItems', 'customerId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'sales_order_id'          => ['nullable', 'exists:sales_orders,id'],
            'customer_id'             => ['nullable', 'exists:customers,id'],
            'material_requirement_id' => ['nullable', 'exists:material_requirements,id'],
            'invoice_number'          => ['required', 'string', 'max:255', 'unique:invoices,invoice_number'],
            'invoice_date'            => ['required', 'date'],
            'due_date'                => ['required', 'date', 'after_or_equal:invoice_date'],
            'gst_type'                => ['nullable', 'string', 'in:cgst_sgst,igst'],
            'freight_terms'           => ['nullable', 'string', 'in:To Pay,To Be Billed,Prepaid,Customer Pickup'],
            'freight_amount'          => ['nullable', 'numeric', 'min:0'],
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

        $salesOrder = !empty($validated['sales_order_id']) ? SalesOrder::find($validated['sales_order_id']) : null;
        $customerId = $validated['customer_id'] ?? $salesOrder?->customer_id;

        if (!$customerId && !empty($validated['material_requirement_id'])) {
            $mr = MaterialRequirement::with('salesOrder')->find($validated['material_requirement_id']);
            $customerId = $mr?->salesOrder?->customer_id;
        }

        if (!$customerId) {
            return redirect()->back()->withInput()->with('error', 'Customer selection is required.');
        }

        $tenantId = $salesOrder?->tenant_id ?? (tenant_id() ?? 1);

        $taxType      = $request->input('tax_type', 'item_wise_tax');
        $discountType = $request->input('discount_type', 'without_discount');
        $gstType      = $request->input('gst_type', 'cgst_sgst');

        $freightTerms  = $validated['freight_terms'] ?? $salesOrder?->freight_terms ?? 'To Pay';
        $freightAmount = floatval($validated['freight_amount'] ?? 0);

        $invoice = DB::transaction(function () use ($validated, $salesOrder, $customerId, $tenantId, $taxType, $discountType, $gstType, $freightTerms, $freightAmount, $request) {
            $subtotal = 0;
            $taxAmount = 0;
            $discountAmount = 0;

            foreach ($validated['items'] as $item) {
                $qty   = floatval($item['quantity']);
                $price = floatval($item['unit_price']);
                $disc  = ($discountType === 'item_wise') ? floatval($item['discount'] ?? 0) : 0;
                $taxR  = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;

                $lineSubtotal = $qty * $price;
                $lineTaxable  = max(0, $lineSubtotal - $disc);
                $lineTax      = $lineTaxable * ($taxR / 100);

                $subtotal       += $lineSubtotal;
                $discountAmount += $disc;
                $taxAmount      += $lineTax;
            }

            if ($discountType === 'order_wise') {
                $discountAmount = floatval($request->input('discount_amount', 0));
            } elseif ($discountType === 'without_discount') {
                $discountAmount = 0;
            }

            if ($taxType === 'order_wise_tax') {
                $orderTaxPercent = floatval($request->input('order_tax_percent', 0));
                $taxableBase     = max(0, $subtotal - $discountAmount);
                $taxAmount       = $taxableBase * ($orderTaxPercent / 100);
            } elseif ($taxType === 'without_tax') {
                $taxAmount = 0;
            }

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $freightTax = ($effectiveFreight > 0 && $taxType !== 'without_tax') ? round($effectiveFreight * 0.18, 2) : 0;
            $totalTaxAmount = $taxAmount + $freightTax;

            $cgstAmount = 0;
            $sgstAmount = 0;
            $igstAmount = 0;

            if ($totalTaxAmount > 0) {
                if ($gstType === 'igst') {
                    $igstAmount = $totalTaxAmount;
                } else {
                    $cgstAmount = round($totalTaxAmount / 2, 2);
                    $sgstAmount = round($totalTaxAmount - $cgstAmount, 2);
                }
            }

            $totalAmount = max(0, $subtotal - $discountAmount + $totalTaxAmount + $effectiveFreight);

            $invoice = Invoice::create([
                'tenant_id'               => $tenantId,
                'sales_order_id'          => $salesOrder?->id,
                'customer_id'             => $customerId,
                'material_requirement_id' => $validated['material_requirement_id'] ?? null,
                'invoice_number'          => $validated['invoice_number'],
                'invoice_date'            => $validated['invoice_date'],
                'due_date'                => $validated['due_date'],
                'status'                  => 'Draft',
                'subtotal'                => $subtotal,
                'tax_amount'              => $totalTaxAmount,
                'gst_type'                => $gstType,
                'cgst_amount'             => $cgstAmount,
                'sgst_amount'             => $sgstAmount,
                'igst_amount'             => $igstAmount,
                'discount_amount'         => $discountAmount,
                'freight_terms'           => $freightTerms,
                'freight_amount'          => $freightAmount,
                'total_amount'            => $totalAmount,
                'amount_paid'             => 0,
                'balance_due'             => $totalAmount,
                'notes'                   => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = floatval($item['quantity']);
                $price = floatval($item['unit_price']);
                $disc = floatval($item['discount'] ?? 0);
                $taxR = floatval($item['tax_rate'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineTaxable = max(0, $lineSubtotal - $disc);
                $lineTax = $lineTaxable * ($taxR / 100);

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
                        $lineCgstAmt = round($lineTax / 2, 2);
                        $lineSgstAmt = round($lineTax - $lineCgstAmt, 2);
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
                    'total_amount'                 => $lineTaxable + $lineTax,
                ]);
            }

            $unallocatedAdvances = $salesOrder ? PaymentAllocation::where('sales_order_id', $salesOrder->id)
                ->whereNull('invoice_id')
                ->get() : collect();

            $remainingBalance = $invoice->balance_due;
            foreach ($unallocatedAdvances as $allocation) {
                if ($remainingBalance <= 0) break;

                $applyAmount = min($allocation->allocated_amount, $remainingBalance);
                $allocation->update([
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => $applyAmount,
                ]);

                $invoice->amount_paid += $applyAmount;
                $invoice->balance_due -= $applyAmount;
                $remainingBalance -= $applyAmount;
            }

            return $invoice;
        });

        event(new InvoicePosted($invoice));

        return redirect()->route('sales.invoices.show', $invoice->id)->with('success', "Invoice {$invoice->invoice_number} created successfully.");
    }

    public function show(int $id): View
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404);
        $this->authorize('view', $invoice);

        $adjustedAmount = $invoice->allocations->sum('allocated_amount');
        $balanceDue     = $invoice->balance_due;

        return view('modules.sales.invoices.show', compact('invoice', 'adjustedAmount', 'balanceDue'));
    }


    public function post(int $id): RedirectResponse
    {
        $invoice = $this->invoiceRepo->find($id);
        if (!$invoice) abort(404);
        $this->authorize('update', $invoice);

        if ($invoice->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only Draft invoices can be posted.']);
        }

        $invoice->status = 'Posted';
        $invoice->save();

        event(new InvoicePosted($invoice));

        return redirect()->route('sales.invoices.show', $invoice->id)->with('success', "Invoice {$invoice->invoice_number} posted successfully.");
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
}
