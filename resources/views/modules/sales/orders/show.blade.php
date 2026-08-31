@extends('layouts.duralux')

@section('title', 'Sales Order Details | SaaS ERP')
@section('page-title', 'Sales Order ' . $order->sales_order_number)
@section('breadcrumb', 'Sales / Sales Orders / ' . $order->sales_order_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-light border me-1" title="Back to Sales Orders" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left me-1"></i>Back
        </a>

        @php
            $totalOrderedQty  = $order->items->sum('quantity');
            $totalInvoicedQty = \App\Domains\Sales\Models\InvoiceItem::whereHas('invoice', function($q) use ($order) {
                $q->where('sales_order_id', $order->id)->where('status', '!=', 'Cancelled');
            })->sum('quantity');
            $hasUnbilledQty = ($totalOrderedQty - $totalInvoicedQty) > 0.0001;
        @endphp

        <!-- Primary Action Buttons (Confirm & Cancel together) -->
        @if ($order->status === 'Draft')
            <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline d-print-none" id="confirmSoForm">
                @csrf
                <button type="button" class="btn btn-sm btn-success fw-bold px-3" onclick="confirmAction({ title: 'Confirm Sales Order', message: 'Confirm Sales Order {{ $order->sales_order_number }}?', variant: 'success', confirmText: 'Confirm' }, function() { document.getElementById('confirmSoForm').submit(); })">
                    <i class="feather-check-circle me-1.5"></i>Confirm Order
                </button>
            </form>

            <form action="{{ route('sales.orders.cancel', $order->id) }}" method="POST" class="d-inline d-print-none" onsubmit="return confirm('Are you sure you want to cancel this sales order?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-light border text-danger fw-bold px-3">
                    <i class="feather-x-circle me-1.5 text-danger"></i>Cancel
                </button>
            </form>
        @elseif (in_array($order->status, ['Confirmed', 'Partially Shipped', 'Shipped']))
            <x-ui.button href="{{ route('sales.dispatches.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" class="fw-bold px-3 d-print-none">
                <i class="feather-truck me-1.5"></i>Dispatch Order
            </x-ui.button>

            @if ($hasUnbilledQty)
                <x-ui.button href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id]) }}" variant="soft-primary" size="sm" class="fw-bold px-3 d-print-none">
                    <i class="feather-file-text me-1.5"></i>Create Invoice
                </x-ui.button>
            @endif

            @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
                <form action="{{ route('sales.orders.cancel', $order->id) }}" method="POST" class="d-inline d-print-none" onsubmit="return confirm('Are you sure you want to cancel this sales order?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light border text-danger fw-bold px-3">
                        <i class="feather-x-circle me-1.5 text-danger"></i>Cancel
                    </button>
                </form>
            @endif
        @endif

        <!-- Action Dropdown using CRM Leads component -->
        @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
            <x-ui.action-dropdown id="soProfileActionsDropdown">
                <li>
                    <a href="{{ route('sales.orders.edit', $order->id) }}" class="dropdown-item py-2">
                        <i class="feather-edit me-2 text-muted fs-12"></i>Edit Sales Order
                    </a>
                </li>
            </x-ui.action-dropdown>
        @endif
    </div>
@endsection

@section('content')

    @if ($errors->any())
        <x-ui.toast :auto="true" type="error" title="{{ $errors->first() }}" />
    @endif

    @php
        $soTabs = [
            ['id' => 'tab-order', 'label' => 'Sales Order Details', 'active' => true, 'icon' => 'feather-shopping-cart'],
            ['id' => 'tab-dispatches', 'label' => 'Delivery Challans / DO (' . $order->dispatches->count() . ')', 'active' => false, 'icon' => 'feather-truck'],
            ['id' => 'tab-invoices', 'label' => 'Invoices (' . $order->invoices->count() . ')', 'active' => false, 'icon' => 'feather-file-text'],
            ['id' => 'tab-payments', 'label' => 'Payments (' . $order->allocations->count() . ')', 'active' => false, 'icon' => 'feather-dollar-sign'],
            ['id' => 'tab-returns', 'label' => 'Returns (' . $order->returns->count() . ')', 'active' => false, 'icon' => 'feather-rotate-ccw'],
        ];
    @endphp

@once
    @push('styles')
        <style>
            /* ── Odoo/Zoho Form Header & Pipeline ─────────────────── */
            .so-status-pipeline {
                display: inline-flex;
                align-items: center;
                border-radius: 6px;
                overflow: hidden;
                border: 1px solid #cbd5e1;
                background-color: #f8fafc;
            }
            .so-status-pipeline .pipeline-step {
                position: relative;
                padding: 6px 16px 6px 24px;
                background-color: #f8fafc;
                color: #64748b;
                font-size: 10.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: inline-flex;
                align-items: center;
                transition: all 0.2s ease;
            }
            .so-status-pipeline .pipeline-step:first-child {
                padding-left: 16px;
            }
            .so-status-pipeline .pipeline-step::after {
                content: "";
                position: absolute;
                top: 0;
                right: -10px;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #f8fafc;
                z-index: 10;
                transition: all 0.2s ease;
            }
            .so-status-pipeline .pipeline-step::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #ffffff;
                z-index: 5;
            }
            .so-status-pipeline .pipeline-step:first-child::before {
                display: none;
            }
            .so-status-pipeline .pipeline-step.active {
                background-color: #1e40af;
                color: #ffffff;
            }
            .so-status-pipeline .pipeline-step.active::after {
                border-left-color: #1e40af;
            }
            .so-status-pipeline .pipeline-step.completed {
                background-color: #e2e8f0;
                color: #334155;
            }
            .so-status-pipeline .pipeline-step.completed::after {
                border-left-color: #e2e8f0;
            }

            /* Tabs styling */
            .so-tab-header-strip {
                background: #ffffff;
                border-bottom: 1px solid #e2e8f0;
                padding: 0 24px;
            }
            #salesOrderTabs .nav-link {
                padding: 12px 18px !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                color: #64748b !important;
                border-bottom: 2px solid transparent !important;
                border-radius: 0 !important;
            }
            #salesOrderTabs .nav-link.active {
                color: #1e40af !important;
                border-bottom-color: #1e40af !important;
                background: transparent !important;
            }
        </style>
    @endpush
@endonce

    <div class="erp-single-panel print-area p-0">
        <!-- Top Status Bar (Chevron Pipeline + Actions) -->
        <div class="d-flex justify-content-between align-items-center bg-white border-bottom px-4 py-2 d-print-none" style="min-height: 52px;">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-12 fw-bold text-dark text-uppercase tracking-wider">Status:</span>
                <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 fw-bold">{{ $order->status }}</span>
            </div>

            <!-- Custom Chevron Status Pipeline -->
            <div class="so-status-pipeline d-print-none">
                @php
                    $statuses = ['Draft', 'Confirmed', 'Shipped'];
                    if ($order->status === 'Partially Shipped') {
                        array_splice($statuses, 2, 0, 'Partially Shipped');
                    }
                    if ($order->status === 'Cancelled') {
                        $statuses[] = 'Cancelled';
                    }
                    $currentIndex = array_search($order->status, $statuses);
                @endphp
                @foreach($statuses as $index => $state)
                    @php
                        $stepClass = '';
                        if ($order->status === $state) {
                            $stepClass = 'active';
                        } elseif ($currentIndex !== false && $index < $currentIndex) {
                            $stepClass = 'completed';
                        }
                    @endphp
                    <span class="pipeline-step {{ $stepClass }}">
                        {{ $state }}
                    </span>
                @endforeach
            </div>
        </div>

        <!-- Navigation Tabs Bar -->
        <div class="so-tab-header-strip d-print-none">
            <x-ui.horizontal-tabs id="salesOrderTabs" :tabs="$soTabs" class="border-0 mb-0" />
        </div>
        
        <div class="card-body p-0">
            <div class="tab-content">
                <!-- TAB 1: Sales Order Details -->
                <div class="tab-pane fade show active p-4" id="tab-order">
                    <!-- Header section -->
                    <div class="row align-items-center mb-4">
                        <div class="col-sm-6 text-start">
                            <div class="d-flex align-items-center">
                                <div class="avatar-text avatar-lg bg-primary text-white fs-4 fw-bold me-3 shadow" style="border-radius: 4px; width: 40px; height: 40px;">
                                    {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                                </div>
                                <div>
                                    <h4 class="fw-bold text-dark mb-0 fs-15">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                    <p class="text-muted mb-0 fs-11">Official Sales Order Document</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0 text-start text-sm-end">
                            <h5 class="fw-bold text-primary mb-1" style="letter-spacing: 0.5px; font-size: 14px;">SALES ORDER</h5>
                            <span class="fs-13 fw-bold text-dark d-block">No: {{ $order->sales_order_number }}</span>
                            @php
                                $badgeClass = 'bg-soft-secondary text-secondary';
                                if ($order->status === 'Confirmed') $badgeClass = 'bg-soft-info text-info';
                                elseif ($order->status === 'Partially Shipped') $badgeClass = 'bg-soft-warning text-warning';
                                elseif ($order->status === 'Shipped') $badgeClass = 'bg-soft-success text-success';
                                elseif ($order->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                            @endphp
                            <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-10 fw-semibold rounded-pill mt-1">{{ $order->status }}</span>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Meta details (Customer / Dates) -->
                    <div class="row mb-4 text-start g-3">
                        <div class="col-sm-4 text-start mb-2 mb-sm-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Customer Info</span>
                            <h6 class="fw-bold text-dark mb-1.5 fs-13">{{ $order->customer?->name ?? '—' }}</h6>
                            <p class="text-muted mb-1 fs-12">Email: {{ $order->customer?->email ?: '—' }}</p>
                            <p class="text-muted mb-0 fs-12">Phone: {{ $order->customer?->phone ?: '—' }}</p>
                        </div>
                        <div class="col-sm-4 text-start mb-2 mb-sm-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Order Schedule</span>
                            <p class="text-dark mb-1 fs-12"><strong>Order Date:</strong> <span class="text-muted ms-1">{{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</span></p>
                            <p class="text-dark mb-1 fs-12"><strong>Est. Shipment:</strong> <span class="text-muted ms-1">{{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : 'Not Scheduled' }}</span></p>
                            <p class="text-dark mb-0 fs-12"><strong>Payment Terms:</strong> <span class="text-muted ms-1">{{ $order->payment_terms ?: 'Due on Receipt' }}</span></p>
                        </div>
                        <div class="col-sm-4 text-sm-end text-start">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Reference Details</span>
                            @if($order->quotation)
                                <p class="text-dark mb-1 fs-12"><strong>Quotation Ref:</strong> <a href="{{ route('crm.quotations.show', $order->quotation_id) }}" class="fw-bold text-primary ms-1">{{ $order->quotation->quotation_number }}</a></p>
                            @endif
                            @if($order->salesPerson)
                                <p class="text-dark mb-0 fs-12"><strong>Sales Rep:</strong> <span class="text-muted ms-1">{{ $order->salesPerson->name }}</span></p>
                            @endif
                        </div>
                    </div>

                    <!-- Addresses grid -->
                    <div class="row mb-4 text-start">
                        <div class="col-12">
                            <div class="border p-3 bg-light bg-opacity-50" style="border-radius: 6px !important; border-color: #cbd5e1 !important;">
                                <div class="row g-3">
                                    <div class="col-md-6 text-start">
                                        <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2" style="letter-spacing: 0.5px;">Billing Address</h6>
                                        <p class="text-muted fs-12 mb-0" style="white-space: pre-line; line-height: 1.5;">{{ $order->billing_address ?: 'No billing address provided.' }}</p>
                                    </div>
                                    <div class="col-md-6 text-start">
                                        <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2" style="letter-spacing: 0.5px;">Shipping Address</h6>
                                        <p class="text-muted fs-12 mb-0" style="white-space: pre-line; line-height: 1.5;">{{ $order->shipping_address ?: 'No shipping address provided.' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive mb-4 border rounded" style="border-radius: 4px; border-color: #cbd5e1 !important;">
                        <table class="table table-hover table-sm table-bordered align-middle mb-0 text-start">
                            <thead class="table-light fs-10 text-uppercase fw-bold text-muted" style="border-bottom: 2px solid #cbd5e1;">
                                <tr>
                                    <th class="ps-3 py-2 text-center" style="width: 4%;">#</th>
                                    <th class="py-2 ps-3" style="width: 36%;">Product Details</th>
                                    <th class="py-2 text-center" style="width: 14%;">Warehouse</th>
                                    <th class="text-center py-2" style="width: 7%;">Qty</th>
                                    <th class="text-end py-2 pe-3" style="width: 11%;">Unit Price</th>
                                    <th class="text-end py-2 pe-3" style="width: 10%;">Discount (₹)</th>
                                    <th class="text-end py-2 pe-3" style="width: 9%;">Taxes (%)</th>
                                    <th class="text-end pe-4 py-2" style="width: 14%;">Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12 text-dark">
                                @foreach ($order->items as $index => $item)
                                    @php
                                        $qty = (float) $item->quantity;
                                        $price = (float) $item->unit_price;
                                        $untaxed = $qty * $price;
                                        $discount = (float) $item->discount;
                                        $taxable = max(0, $untaxed - $discount);
                                        $taxRate = (float) $item->tax_rate;
                                        $lineTax = ($order->tax_type === 'order_wise_tax') ? 0 : ($taxable * ($taxRate / 100));
                                        $lineTotalInclTax = $taxable + $lineTax;
                                    @endphp
                                    <tr>
                                        <td class="ps-3 text-muted text-center py-1.5">{{ $index + 1 }}</td>
                                        <td class="py-1.5 ps-3">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                                <div>
                                                    <strong class="text-dark">{{ $item->item_name }}</strong>
                                                    @if($item->product?->sku)
                                                        <span class="text-muted ms-1" style="font-size: 10px;">(SKU: {{ $item->product->sku }})</span>
                                                    @endif
                                                    @if($item->description)
                                                        <small class="text-muted d-block mt-0.5 font-italic" style="font-size: 10px;">{{ $item->description }}</small>
                                                    @endif
                                                </div>
                                                <div class="text-end ms-2">
                                                    @php
                                                        $method = $item->product?->supplier_method ?? 'buy';
                                                    @endphp
                                                    @if ($method === 'manufacture')
                                                        <span class="badge bg-soft-warning text-warning px-1.5 py-0.2 fs-9 fw-semibold rounded-pill">Manufacture</span>
                                                        @php
                                                            $linkedMo = $order->productionOrders->firstWhere('sales_order_item_id', $item->id);
                                                        @endphp
                                                        @if ($linkedMo)
                                                            <div class="mt-0.5">
                                                                <a href="{{ route('production.orders.show', $linkedMo->id) }}" class="text-primary fw-bold fs-9 bg-soft-primary px-1 py-0.2 rounded border border-primary border-opacity-10">
                                                                    <i class="feather-cpu" style="font-size: 8px;"></i> {{ $linkedMo->order_number }}
                                                                </a>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-soft-success text-success px-1.5 py-0.2 fs-9 fw-semibold rounded-pill">Trade</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-1.5 text-center">
                                            <div class="d-inline-flex align-items-center text-muted" style="font-size: 11px;">
                                                {{ $item->warehouse?->name ?: '—' }}
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold py-1.5">{{ $item->quantity }}</td>
                                        <td class="text-end text-muted py-1.5 pe-3">₹{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end text-danger py-1.5 pe-3">
                                            @if($item->discount > 0)
                                                -₹{{ number_format($item->discount, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end text-muted py-1.5 pe-3">
                                            @if($order->tax_type !== 'without_tax')
                                                {{ number_format($item->tax_rate, 2) }}%
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-dark py-1.5">₹{{ number_format($lineTotalInclTax, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals & Calculations -->
                    <div class="row g-4 text-start">
                        <div class="col-sm-7 text-start">
                            @if($order->terms_conditions)
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark mb-1.5 fs-12 text-uppercase" style="letter-spacing: 0.5px;">Terms & Conditions</h6>
                                    <div class="text-muted fs-11 terms-conditions-content">{!! $order->terms_conditions !!}</div>
                                </div>
                            @endif

                            @if($order->notes)
                                <div>
                                    <h6 class="fw-bold text-dark mb-1.5 fs-12 text-uppercase" style="letter-spacing: 0.5px;">Internal Notes / Remarks</h6>
                                    <p class="text-muted fs-11 mb-0" style="white-space: pre-line; line-height: 1.4;">{{ $order->notes }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="col-sm-5 ms-auto">
                            <div class="p-3 rounded-3 border bg-light text-dark" style="border-color: #cbd5e1 !important; background-color: #f8fafc !important;">
                                @php
                                    $grossSubtotal = 0;
                                    $totalItemDiscount = 0;
                                    $itemsTaxAmount = 0;
                                    $maxItemTaxRate = 0;
                                    foreach($order->items as $it) {
                                        $grossSubtotal += ($it->quantity * $it->unit_price);
                                        $totalItemDiscount += $it->discount;
                                        if ($order->tax_type === 'item_wise_tax') {
                                            $lineTaxable = max(0, ($it->quantity * $it->unit_price) - $it->discount);
                                            $itemsTaxAmount += ($lineTaxable * ($it->tax_rate / 100));
                                            if ($it->tax_rate > $maxItemTaxRate) $maxItemTaxRate = $it->tax_rate;
                                        }
                                    }
                                    $effectiveDiscount = ($order->discount_type === 'order_wise') ? (float)$order->discount : $totalItemDiscount;
                                    $taxableBase = max(0, $grossSubtotal - $effectiveDiscount);

                                    if ($order->tax_type === 'order_wise_tax') {
                                        $itemsTaxAmount = $taxableBase * (($order->order_tax_rate ?: 18) / 100);
                                    } elseif ($order->tax_type === 'without_tax') {
                                        $itemsTaxAmount = 0;
                                    }

                                    $itemsTotalInclGst = $taxableBase + $itemsTaxAmount;
                                    $freightAmount = ($order->freight_terms === 'To Be Billed') ? (float)($order->freight_amount ?: $order->shipping_charges ?: 0) : 0;
                                    $adjustment = (float)$order->adjustment;
                                    $grandTotal = $itemsTotalInclGst + $freightAmount + $adjustment;
                                    $gstType = $order->gst_type ?? 'cgst_sgst';
                                @endphp

                                <!-- 1. Subtotal (Excl. Tax) -->
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">Subtotal (Excl. Tax):</span>
                                    <span class="fw-bold text-dark">₹{{ number_format($grossSubtotal, 2) }}</span>
                                </div>

                                <!-- 2. Less: Item Discounts -->
                                @if($order->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12 text-danger">
                                        <span class="fw-semibold">Less: Item Discounts:</span>
                                        <span class="fw-bold">-₹{{ number_format($effectiveDiscount, 2) }}</span>
                                    </div>
                                @endif

                                <!-- 3. Items Taxable Value -->
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">Items Taxable Value:</span>
                                    <span class="fw-bold text-dark">₹{{ number_format($taxableBase, 2) }}</span>
                                </div>

                                <!-- 4. Add: CGST / SGST or IGST Breakdown -->
                                @if($order->tax_type !== 'without_tax' && $itemsTaxAmount > 0)
                                    @if($gstType === 'cgst_sgst')
                                        <div class="d-flex justify-content-between align-items-center mb-1.5 fs-12">
                                            <span class="text-muted fw-medium">Add: CGST (Central Tax):</span>
                                            <span class="text-muted font-monospace">+₹{{ number_format($itemsTaxAmount / 2, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                            <span class="text-muted fw-medium">Add: SGST (State Tax):</span>
                                            <span class="text-muted font-monospace">+₹{{ number_format($itemsTaxAmount / 2, 2) }}</span>
                                        </div>
                                    @else
                                        <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                            <span class="text-muted fw-medium">Add: IGST (Integrated Tax):</span>
                                            <span class="text-muted font-monospace">+₹{{ number_format($itemsTaxAmount, 2) }}</span>
                                        </div>
                                    @endif
                                @endif

                                <!-- 5. Billed Items Total (Incl. GST) -->
                                <div class="d-flex justify-content-between align-items-center my-2 py-1.5 px-2.5 rounded bg-white border fs-12 fw-bold text-dark" style="border-color: #e2e8f0 !important;">
                                    <span>Billed Items Total (Incl. GST):</span>
                                    <span>₹{{ number_format($itemsTotalInclGst, 2) }}</span>
                                </div>

                                <!-- 6. Freight Charges -->
                                @if($freightAmount > 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-semibold">Freight Charges:</span>
                                        <span class="fw-bold text-primary">₹{{ number_format($freightAmount, 2) }}</span>
                                    </div>
                                @endif

                                <!-- 7. Adjustment -->
                                @if($adjustment != 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-semibold">Adjustment:</span>
                                        <span class="fw-bold text-dark">₹{{ number_format($adjustment, 2) }}</span>
                                    </div>
                                @endif

                                <!-- 8. Grand Total -->
                                <div class="d-flex justify-content-between align-items-center pt-2.5 border-top mt-2" style="border-color: #cbd5e1 !important;">
                                    <span class="fw-bold text-dark fs-13 text-uppercase" style="letter-spacing: 0.5px;">Grand Total:</span>
                                    <span class="fw-bold text-primary fs-16">₹{{ number_format($grandTotal, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signature block -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-6 text-start">
                            <p class="fs-10 text-muted mb-0">For queries regarding fulfillment, please refer to the sales department.</p>
                        </div>
                    </div>
                </div>

                <!-- TAB: Delivery Challans (Dispatches) -->
                <div class="tab-pane fade" id="tab-dispatches">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-20">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-truck fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">Delivery Challans / Dispatches (DO)</h6>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soDispatchesTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 17%;">Dispatch #</th>
                                    <th style="width: 12%;">Date</th>
                                    <th style="width: 22%;">Transporter / Carrier</th>
                                    <th style="width: 14%;">Vehicle No</th>
                                    <th style="width: 10%;">Status</th>
                                    <th class="text-end pe-4" style="width: 25%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->dispatches as $dispatch)
                                    @php
                                        $dispBadge = 'bg-soft-secondary text-secondary';
                                        if ($dispatch->status === 'Pending') $dispBadge = 'bg-soft-warning text-warning';
                                        elseif ($dispatch->status === 'Confirmed') $dispBadge = 'bg-soft-primary text-primary';
                                        elseif ($dispatch->status === 'Dispatched' || $dispatch->status === 'Shipped') $dispBadge = 'bg-soft-info text-info';
                                        elseif ($dispatch->status === 'Delivered') $dispBadge = 'bg-soft-success text-success';
                                        elseif ($dispatch->status === 'Cancelled') $dispBadge = 'bg-soft-danger text-danger';

                                        $isDispatchInvoiced = false;
                                        if ($dispatch->status === 'Invoiced') {
                                            $isDispatchInvoiced = true;
                                        } elseif ($order->invoices) {
                                            $isDispatchInvoiced = $order->invoices->where('status', '!=', 'Cancelled')->contains(function($inv) use ($dispatch) {
                                                return ($dispatch->material_requirement_id && $inv->material_requirement_id == $dispatch->material_requirement_id);
                                            });
                                        }
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="text-primary">{{ $dispatch->dispatch_number }}</a>
                                        </td>
                                        <td class="text-muted">{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d/m/Y') : '—' }}</td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: '—') }}</span>
                                        </td>
                                        <td class="font-monospace text-muted">{{ $dispatch->vehicle_number ?: '—' }}</td>
                                        <td>
                                            <span class="badge {{ $dispBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $dispatch->status }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="hstack gap-2 justify-content-end align-items-center">
                                                @if(!$isDispatchInvoiced && in_array($dispatch->status, ['Confirmed', 'Shipped', 'Dispatched', 'Delivered']))
                                                    <x-ui.button href="{!! route('sales.invoices.create', ['dispatch_order_id' => $dispatch->id, 'material_requirement_id' => $dispatch->material_requirement_id, 'sales_order_id' => $order->id, 'mode' => 'dispatch_order']) !!}" variant="soft-primary" size="xs" icon="feather-file-text" class="fw-bold px-2.5 py-1 fs-11 text-nowrap" title="Create Invoice against this Dispatch Order">
                                                        Create Invoice
                                                    </x-ui.button>
                                                @elseif($isDispatchInvoiced)
                                                    <span class="badge bg-soft-success text-success fs-10 fw-semibold px-2 py-0.5"><i class="feather-check me-1"></i>Invoiced</span>
                                                @endif

                                                <x-ui.action-dropdown :viewUrl="route('sales.dispatches.show', $dispatch->id)" id="dispAction-{{ $dispatch->id }}">
                                                    <x-ui.dropdown-item href="{{ route('sales.dispatches.show', $dispatch->id) }}" icon="feather-eye me-2">
                                                        View Dispatch
                                                    </x-ui.dropdown-item>
                                                    @if(!$isDispatchInvoiced && in_array($dispatch->status, ['Confirmed', 'Shipped', 'Dispatched', 'Delivered']))
                                                        <x-ui.dropdown-item href="{!! route('sales.invoices.create', ['dispatch_order_id' => $dispatch->id, 'material_requirement_id' => $dispatch->material_requirement_id, 'sales_order_id' => $order->id, 'mode' => 'dispatch_order']) !!}" icon="feather-file-text me-2" class="text-success fw-semibold">
                                                            Create Invoice
                                                        </x-ui.dropdown-item>
                                                    @endif
                                                    <x-ui.dropdown-item href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}" icon="feather-download me-2" target="_blank">
                                                        Download Challan PDF
                                                    </x-ui.dropdown-item>
                                                </x-ui.action-dropdown>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-truck fs-1 mb-2 d-block text-gray-300"></i>
                                            No Delivery Challans (Dispatches) generated for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- TAB 2: Invoices -->
                <div class="tab-pane fade" id="tab-invoices">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-20">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-file-text fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">Sales Invoices</h6>
                        </div>
                        @if (($order->status === 'Confirmed' || $order->status === 'Partially Shipped' || $order->status === 'Shipped') && ($hasUnbilledQty ?? true))
                            <x-ui.button href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                Create Invoice
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soInvoicesTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">Invoice Number</th>
                                    <th style="width: 18%;">Date</th>
                                    <th style="width: 25%;">Source Shipment</th>
                                    <th class="text-end" style="width: 17%;">Grand Total</th>
                                    <th style="width: 15%;">Status</th>
                                    <th class="text-end pe-4" style="width: 15%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->invoices as $inv)
                                    @php
                                        $invBadge = 'bg-soft-secondary text-secondary';
                                        if ($inv->status === 'Paid') $invBadge = 'bg-soft-success text-success';
                                        elseif ($inv->status === 'Partially Paid') $invBadge = 'bg-soft-warning text-warning';
                                        elseif ($inv->status === 'Sent') $invBadge = 'bg-soft-info text-info';
                                        elseif ($inv->status === 'Cancelled') $invBadge = 'bg-soft-danger text-danger';
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.invoices.show', $inv->id) }}" class="text-primary">{{ $inv->invoice_number }}</a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($inv->invoice_date)) }}</td>
                                        <td>
                                            @if ($inv->materialRequirement)
                                                <a href="{{ route('sales.material-requirements.show', $inv->material_requirement_id) }}" class="text-muted fw-semibold">
                                                    {{ $inv->materialRequirement->requirement_number }}
                                                </a>
                                            @else
                                                <span class="text-muted fs-12">Full Order Billing</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($inv->grand_total, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $invBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $inv->status }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.invoices.show', $inv->id)" id="invAction-{{ $inv->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.invoices.show', $inv->id) }}" icon="feather-eye me-2">
                                                    View Invoice
                                                </x-ui.dropdown-item>

                                                @if(in_array($inv->status, ['Sent', 'Partially Paid', 'Posted', 'Draft']))
                                                    <x-ui.dropdown-item href="{{ route('sales.payments.create', ['invoice_id' => $inv->id, 'customer_id' => $order->customer_id]) }}" icon="feather-dollar-sign me-2" class="text-success fw-semibold">
                                                        Register Payment
                                                    </x-ui.dropdown-item>
                                                @endif
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-file-text fs-1 mb-2 d-block text-gray-300"></i>
                                            No invoices generated for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- TAB 3: Payments & Advance Allocations -->
                <div class="tab-pane fade" id="tab-payments">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-20">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-dollar-sign fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">Payments & Advance Allocations</h6>
                        </div>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                            <x-ui.button href="{{ route('sales.payments.create', ['sales_order_id' => $order->id, 'customer_id' => $order->customer_id, 'allocate_to' => 'sales_order']) }}" variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                Record Receipt / Advance
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soPaymentsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">Payment Number</th>
                                    <th style="width: 20%;">Date</th>
                                    <th style="width: 20%;">Method</th>
                                    <th style="width: 20%;">Reference No</th>
                                    <th class="text-end" style="width: 20%;">Allocated Amount</th>
                                    <th class="text-end pe-4" style="width: 15%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->allocations as $alloc)
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.payments.show', $alloc->payment->id) }}" class="text-primary">{{ $alloc->payment->payment_number }}</a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($alloc->payment->payment_date)) }}</td>
                                        <td><span class="fw-semibold text-dark">{{ $alloc->payment->payment_method }}</span></td>
                                        <td class="text-muted">{{ $alloc->payment->reference_no ?: '—' }}</td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($alloc->allocated_amount, 2) }}</td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.payments.show', $alloc->payment->id)" id="payAction-{{ $alloc->payment->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.payments.show', $alloc->payment->id) }}" icon="feather-eye me-2">
                                                    View Receipt
                                                </x-ui.dropdown-item>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-dollar-sign fs-1 mb-2 d-block text-gray-300"></i>
                                            No payment receipts or advances adjusted for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- TAB 4: Returns -->
                <div class="tab-pane fade" id="tab-returns">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-20">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-rotate-ccw fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">Sales Returns</h6>
                        </div>
                        @if ($order->status === 'Partially Shipped' || $order->status === 'Shipped')
                            <x-ui.button href="{{ route('sales.returns.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                Create Sales Return
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soReturnsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">Return Number</th>
                                    <th style="width: 20%;">Date</th>
                                    <th class="text-end" style="width: 20%;">Refund Amount</th>
                                    <th style="width: 20%;">Status</th>
                                    <th class="text-end pe-4" style="width: 15%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->returns as $ret)
                                    @php
                                        $retBadge = 'bg-soft-secondary text-secondary';
                                        if ($ret->status === 'Completed') $retBadge = 'bg-soft-success text-success';
                                        elseif ($ret->status === 'Cancelled') $retBadge = 'bg-soft-danger text-danger';
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.returns.show', $ret->id) }}" class="text-primary">{{ $ret->return_number }}</a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($ret->return_date)) }}</td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($ret->total_refund_amount, 2) }}</td>
                                        <td><span class="badge {{ $retBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $ret->status }}</span></td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.returns.show', $ret->id)" id="retAction-{{ $ret->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.returns.show', $ret->id) }}" icon="feather-eye me-2">
                                                    View Return
                                                </x-ui.dropdown-item>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="feather-rotate-ccw fs-1 mb-2 d-block text-gray-300"></i>
                                            No sales returns created for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .terms-conditions-content p {
            margin-bottom: 4px !important;
            line-height: 1.4 !important;
        }
        .terms-conditions-content p:last-child {
            margin-bottom: 0 !important;
        }

        @media print {
            @page {
                margin: 0 !important;
            }

            .nxl-sidebar,
            .nxl-header,
            .page-header,
            .d-print-none,
            .alert,
            header,
            footer,
            aside,
            nav {
                display: none !important;
            }

            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 8mm 12mm !important;
            }

            .nxl-container,
            .nxl-content,
            .main-content,
            .content-body,
            .container-fluid {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                transform: none !important;
                top: 0 !important;
                position: static !important;
            }

            .print-area {
                border: 0 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                position: static !important;
            }

            .card-body.p-5,
            .tab-pane.p-5 {
                padding: 0 !important;
            }

            .mb-5 {
                margin-bottom: 1rem !important;
            }
            .my-5 {
                margin-top: 1rem !important;
                margin-bottom: 1rem !important;
            }
            .mt-5 {
                margin-top: 1rem !important;
            }
            .mb-4 {
                margin-bottom: 0.75rem !important;
            }
            hr {
                margin: 0.75rem 0 !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            // Switch tab based on URL hash if present
            var hash = window.location.hash;
            if (hash) {
                var triggerEl = document.querySelector('#salesOrderTabs button[data-bs-target="' + hash + '"]') 
                             || document.querySelector('#salesOrderTabs a[href="' + hash + '"]');
                if (triggerEl) {
                    var tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                }
            }
        });
    </script>
@endpush
