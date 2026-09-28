@extends('layouts.duralux')

@section('title', __('crm.sales_order_details') . ' | SaaS ERP')
@section('page-title', __('crm.sales_order_label') . ' ' . $order->sales_order_number)
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.sales_orders') . ' / ' . $order->sales_order_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-light border me-1" title="{{ __('crm.back_to_listing') }}" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left me-1"></i>{{ __('crm.back') }}
        </a>

        @php
            $totalOrderedQty  = $order->items->sum('quantity');
            $totalInvoicedQty = \App\Domains\Sales\Models\InvoiceItem::whereHas('invoice', function($q) use ($order) {
                $q->where('sales_order_id', $order->id)->where('status', '!=', 'Cancelled');
            })->sum('quantity');
            $hasUnbilledQty = ($totalOrderedQty - $totalInvoicedQty) > 0.0001;
            $invoicingPolicy = tenant()?->settings['invoicing_policy'] ?? 'both';
        @endphp

        <!-- Primary Action Buttons (Confirm & Cancel together) -->
        @if ($order->status === 'Draft')
            <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline d-print-none" id="confirmSoForm">
                @csrf
                <button type="button" class="btn btn-sm btn-success fw-bold px-3" onclick="confirmAction({ title: '{{ __('crm.confirm_order') }}', message: 'Confirm Sales Order {{ $order->sales_order_number }}?', variant: 'success', confirmText: '{{ __('crm.confirm') }}' }, function() { document.getElementById('confirmSoForm').submit(); })">
                    <i class="feather-check-circle me-1.5"></i>{{ __('crm.confirm_order') }}
                </button>
            </form>

            <form action="{{ route('sales.orders.cancel', $order->id) }}" method="POST" class="d-inline d-print-none" onsubmit="return confirm('Are you sure you want to cancel this sales order?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-light border text-danger fw-bold px-3">
                    <i class="feather-x-circle me-1.5 text-danger"></i>{{ __('crm.cancel') }}
                </button>
            </form>
        @elseif (in_array($order->status, ['Confirmed', 'Partially Shipped', 'Shipped']))
            @if ($hasUnbilledQty && $invoicingPolicy !== 'dispatch_order')
                <a href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id, 'mode' => 'sales_order']) }}" class="btn btn-sm btn-primary fw-bold px-3 d-print-none">
                    <i class="feather-file-text me-1.5"></i>{{ __('crm.create_invoice') }}
                </a>
            @endif

            @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
                <form action="{{ route('sales.orders.cancel', $order->id) }}" method="POST" class="d-inline d-print-none" onsubmit="return confirm('Are you sure you want to cancel this sales order?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light border text-danger fw-bold px-3">
                        <i class="feather-x-circle me-1.5 text-danger"></i>{{ __('crm.cancel') }}
                    </button>
                </form>
            @endif
        @endif

        <!-- Action Dropdown using CRM Leads component -->
        @if ($order->status === 'Draft')
            <x-ui.action-dropdown id="soProfileActionsDropdown">
                <li>
                    <a href="{{ route('sales.orders.edit', $order->id) }}" class="dropdown-item py-2">
                        <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('crm.edit_sales_order') }}
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
            ['id' => 'tab-order', 'label' => __('crm.tab_so_details'), 'active' => true, 'icon' => 'feather-shopping-cart'],
            ['id' => 'tab-dispatches', 'label' => __('crm.tab_dispatches') . ' (' . $order->dispatches->count() . ')', 'active' => false, 'icon' => 'feather-truck'],
            ['id' => 'tab-invoices', 'label' => __('crm.tab_invoices') . ' (' . $order->invoices->count() . ')', 'active' => false, 'icon' => 'feather-file-text'],
            ['id' => 'tab-payments', 'label' => __('crm.tab_payments') . ' (' . $order->allocations->count() . ')', 'active' => false, 'icon' => 'feather-dollar-sign'],
            ['id' => 'tab-returns', 'label' => __('crm.tab_returns') . ' (' . $order->returns->count() . ')', 'active' => false, 'icon' => 'feather-rotate-ccw'],
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

            /* Tabs container styling */
            .so-tab-header-strip {
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                padding: 10px 24px;
            }
        </style>
    @endpush
@endonce

    <div class="erp-single-panel print-area p-0">
        <!-- Top Status Bar (Chevron Pipeline + Actions) -->
        <div class="d-flex justify-content-between align-items-center bg-white border-bottom px-4 py-2 d-print-none" style="min-height: 52px;">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-12 fw-bold text-dark text-uppercase tracking-wider">{{ __('crm.status') }}:</span>
                <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 fw-bold">
                    @if($order->status === 'Draft') {{ __('crm.status_draft') }}
                    @elseif($order->status === 'Confirmed') {{ __('crm.status_confirmed') }}
                    @elseif($order->status === 'Partially Shipped') {{ __('crm.status_partially_shipped') }}
                    @elseif($order->status === 'Shipped') {{ __('crm.status_shipped') }}
                    @elseif($order->status === 'Cancelled') {{ __('crm.status_cancelled') }}
                    @else {{ $order->status }}
                    @endif
                </span>
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
                        @if($state === 'Draft') {{ __('crm.status_draft') }}
                        @elseif($state === 'Confirmed') {{ __('crm.status_confirmed') }}
                        @elseif($state === 'Partially Shipped') {{ __('crm.status_partially_shipped') }}
                        @elseif($state === 'Shipped') {{ __('crm.status_shipped') }}
                        @elseif($state === 'Cancelled') {{ __('crm.status_cancelled') }}
                        @else {{ $state }}
                        @endif
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
                                    <p class="text-muted mb-0 fs-11">{{ __('crm.official_so_doc') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0 text-start text-sm-end">
                            <h5 class="fw-bold text-primary mb-1" style="letter-spacing: 0.5px; font-size: 14px;">{{ __('crm.sales_order_label') }}</h5>
                            <span class="fs-13 fw-bold text-dark d-block">{{ __('crm.no_colon') }} {{ $order->sales_order_number }}</span>
                            @php
                                $badgeClass = 'bg-soft-secondary text-secondary';
                                if ($order->status === 'Confirmed') $badgeClass = 'bg-soft-info text-info';
                                elseif ($order->status === 'Partially Shipped') $badgeClass = 'bg-soft-warning text-warning';
                                elseif ($order->status === 'Shipped') $badgeClass = 'bg-soft-success text-success';
                                elseif ($order->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                            @endphp
                            <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-10 fw-semibold rounded-pill mt-1">
                                @if($order->status === 'Draft') {{ __('crm.status_draft') }}
                                @elseif($order->status === 'Confirmed') {{ __('crm.status_confirmed') }}
                                @elseif($order->status === 'Partially Shipped') {{ __('crm.status_partially_shipped') }}
                                @elseif($order->status === 'Shipped') {{ __('crm.status_shipped') }}
                                @elseif($order->status === 'Cancelled') {{ __('crm.status_cancelled') }}
                                @else {{ $order->status }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Meta details (Customer / Dates) -->
                    <div class="row mb-4 text-start g-3">
                        <div class="col-sm-4 text-start mb-2 mb-sm-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">{{ __('crm.customer_info') }}</span>
                            <h6 class="fw-bold text-dark mb-1.5 fs-13">{{ $order->customer?->name ?? '—' }}</h6>
                            <p class="text-muted mb-1 fs-12">{{ __('crm.email') }}: {{ $order->customer?->email ?: '—' }}</p>
                            <p class="text-muted mb-0 fs-12">{{ __('crm.contact_phone') }}: {{ $order->customer?->phone ?: '—' }}</p>
                        </div>
                        <div class="col-sm-4 text-start mb-2 mb-sm-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">{{ __('crm.order_schedule') }}</span>
                            <p class="text-dark mb-1 fs-12"><strong>{{ __('crm.order_date') }}:</strong> <span class="text-muted ms-1">{{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</span></p>
                            <p class="text-dark mb-1 fs-12"><strong>{{ __('crm.est_shipment') }}</strong> <span class="text-muted ms-1">{{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : __('crm.not_scheduled') }}</span></p>
                            <p class="text-dark mb-0 fs-12"><strong>{{ __('crm.payment_terms') }}</strong> <span class="text-muted ms-1">{{ \App\Domains\Platform\Models\PaymentTerm::getLabel($order->payment_terms) }}</span></p>
                        </div>
                        <div class="col-sm-4 text-sm-end text-start">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">{{ __('crm.reference_details') }}</span>
                            @if($order->quotation)
                                <p class="text-dark mb-1 fs-12"><strong>{{ __('crm.quotation_ref') }}:</strong> <a href="{{ route('crm.quotations.show', $order->quotation_id) }}" class="fw-bold text-primary ms-1">{{ $order->quotation->quotation_number }}</a></p>
                            @endif
                            @if($order->salesPerson)
                                <p class="text-dark mb-0 fs-12"><strong>{{ __('crm.sales_rep') }}:</strong> <span class="text-muted ms-1">{{ $order->salesPerson->name }}</span></p>
                            @endif
                        </div>
                    </div>

                    <!-- Addresses grid -->
                    <div class="row mb-4 text-start">
                        <div class="col-12">
                            <div class="border p-3 bg-light bg-opacity-50" style="border-radius: 6px !important; border-color: #cbd5e1 !important;">
                                <div class="row g-3">
                                    <div class="col-md-6 text-start">
                                        <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2" style="letter-spacing: 0.5px;">{{ __('crm.billing_address') }}</h6>
                                        <p class="text-muted fs-12 mb-0" style="white-space: pre-line; line-height: 1.5;">{{ $order->billing_address ?: __('crm.no_billing_address') }}</p>
                                    </div>
                                    <div class="col-md-6 text-start">
                                        <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2" style="letter-spacing: 0.5px;">{{ __('crm.shipping_address') }}</h6>
                                        <p class="text-muted fs-12 mb-0" style="white-space: pre-line; line-height: 1.5;">{{ $order->shipping_address ?: __('crm.no_shipping_address') }}</p>
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
                                    <th class="py-2 ps-3" style="width: 36%;">{{ __('crm.product_details') }}</th>
                                    <th class="py-2 text-center" style="width: 14%;">{{ __('crm.warehouse') }}</th>
                                    <th class="text-center py-2" style="width: 7%;">{{ __('crm.qty') }}</th>
                                    <th class="text-end py-2 pe-3" style="width: 11%;">{{ __('crm.unit_price') }}</th>
                                    <th class="text-end py-2 pe-3" style="width: 10%;">{{ __('crm.discount') }}</th>
                                    <th class="text-end py-2 pe-3" style="width: 9%;">{{ __('crm.taxes_percent') }}</th>
                                    <th class="text-end pe-4 py-2" style="width: 14%;">{{ __('crm.amount') }}</th>
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
                                        <td class="text-end text-muted py-1.5 pe-3">{{ format_currency($item->unit_price) }}</td>
                                        <td class="text-end text-danger py-1.5 pe-3">
                                            @if($item->discount > 0)
                                                -{{ format_currency($item->discount) }}
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
                                        <td class="text-end pe-4 fw-bold text-dark py-1.5">{{ format_currency($lineTotalInclTax) }}</td>
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
                                    <h6 class="fw-bold text-dark mb-1.5 fs-12 text-uppercase" style="letter-spacing: 0.5px;">{{ __('crm.terms_conditions') }}</h6>
                                    <div class="text-muted fs-11 terms-conditions-content">{!! $order->terms_conditions !!}</div>
                                </div>
                            @endif

                            @if($order->notes)
                                <div>
                                    <h6 class="fw-bold text-dark mb-1.5 fs-12 text-uppercase" style="letter-spacing: 0.5px;">{{ __('crm.internal_notes_remarks') }}</h6>
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
                                    <span class="text-muted fw-semibold">{{ __('crm.subtotal_excl_tax') }}</span>
                                    <span class="fw-bold text-dark">{{ format_currency($grossSubtotal) }}</span>
                                </div>

                                <!-- 2. Less: Item Discounts -->
                                @if($order->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12 text-danger">
                                        <span class="fw-semibold">{{ __('crm.less_item_discounts') }}</span>
                                        <span class="fw-bold">-{{ format_currency($effectiveDiscount) }}</span>
                                    </div>
                                @endif

                                <!-- 3. Items Taxable Value -->
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">{{ __('crm.items_taxable_value') }}</span>
                                    <span class="fw-bold text-dark">{{ format_currency($taxableBase) }}</span>
                                </div>

                                <!-- 4. Add: CGST / SGST or IGST Breakdown -->
                                @if($order->tax_type !== 'without_tax' && $itemsTaxAmount > 0)
                                    @if($gstType === 'cgst_sgst')
                                        <div class="d-flex justify-content-between align-items-center mb-1.5 fs-12">
                                            <span class="text-muted fw-medium">{{ __('crm.add_cgst') }}</span>
                                            <span class="text-muted font-monospace">+{{ format_currency($itemsTaxAmount / 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                            <span class="text-muted fw-medium">{{ __('crm.add_sgst') }}</span>
                                            <span class="text-muted font-monospace">+{{ format_currency($itemsTaxAmount / 2) }}</span>
                                        </div>
                                    @else
                                        <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                            <span class="text-muted fw-medium">{{ __('crm.add_igst') }}</span>
                                            <span class="text-muted font-monospace">+{{ format_currency($itemsTaxAmount) }}</span>
                                        </div>
                                    @endif
                                @endif

                                <!-- 5. Billed Items Total (Incl. GST) -->
                                <div class="d-flex justify-content-between align-items-center my-2 py-1.5 px-2.5 rounded bg-white border fs-12 fw-bold text-dark" style="border-color: #e2e8f0 !important;">
                                    <span>{{ __('crm.billed_items_total') }}</span>
                                    <span>{{ format_currency($itemsTotalInclGst) }}</span>
                                </div>

                                <!-- 6. Freight Charges -->
                                @if($freightAmount > 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-semibold">{{ __('crm.freight_charges') }}</span>
                                        <span class="fw-bold text-primary">{{ format_currency($freightAmount) }}</span>
                                    </div>
                                @endif

                                <!-- 7. Adjustment -->
                                @if($adjustment != 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-semibold">{{ __('crm.adjustment') }}</span>
                                        <span class="fw-bold text-dark">{{ format_currency($adjustment) }}</span>
                                    </div>
                                @endif

                                <!-- 8. Grand Total -->
                                <div class="d-flex justify-content-between align-items-center pt-2.5 border-top mt-2" style="border-color: #cbd5e1 !important;">
                                    <span class="fw-bold text-dark fs-13 text-uppercase" style="letter-spacing: 0.5px;">{{ __('crm.grand_total') }}</span>
                                    <span class="fw-bold text-primary fs-16">{{ format_currency($grandTotal) }}</span>
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
                            <h6 class="mb-0 fw-bold text-dark fs-14">{{ __('crm.delivery_challans_dispatches') }}</h6>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soDispatchesTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 17%;">{{ __('crm.dispatch_num') }}</th>
                                    <th style="width: 12%;">{{ __('crm.date') }}</th>
                                    <th style="width: 22%;">{{ __('crm.transporter_carrier') }}</th>
                                    <th style="width: 14%;">{{ __('crm.vehicle_no') }}</th>
                                    <th style="width: 10%;">{{ __('crm.status') }}</th>
                                    <th class="text-end pe-4" style="width: 25%;">{{ __('crm.actions') }}</th>
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
                                        if ($dispatch->status === 'Invoiced' || !empty($dispatch->invoice_id)) {
                                            $isDispatchInvoiced = true;
                                        } elseif (!$hasUnbilledQty) {
                                            $isDispatchInvoiced = true;
                                        } elseif ($order->invoices) {
                                            $isDispatchInvoiced = $order->invoices->where('status', '!=', 'Cancelled')->contains(function($inv) use ($dispatch) {
                                                return ($dispatch->invoice_id && $inv->id == $dispatch->invoice_id)
                                                    || ($dispatch->material_requirement_id && $inv->material_requirement_id == $dispatch->material_requirement_id);
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
                                                        {{ __('crm.create_invoice_btn') }}
                                                    </x-ui.button>
                                                @elseif($isDispatchInvoiced)
                                                    <span class="badge bg-soft-success text-success fs-10 fw-semibold px-2 py-0.5"><i class="feather-check me-1"></i>{{ __('crm.invoiced_badge') }}</span>
                                                @endif

                                                <x-ui.action-dropdown :viewUrl="route('sales.dispatches.show', $dispatch->id)" id="dispAction-{{ $dispatch->id }}">
                                                    <x-ui.dropdown-item href="{{ route('sales.dispatches.show', $dispatch->id) }}" icon="feather-eye me-2">
                                                        {{ __('crm.view_dispatch') }}
                                                    </x-ui.dropdown-item>
                                                    @if(!$isDispatchInvoiced && in_array($dispatch->status, ['Confirmed', 'Shipped', 'Dispatched', 'Delivered']))
                                                        <x-ui.dropdown-item href="{!! route('sales.invoices.create', ['dispatch_order_id' => $dispatch->id, 'material_requirement_id' => $dispatch->material_requirement_id, 'sales_order_id' => $order->id, 'mode' => 'dispatch_order']) !!}" icon="feather-file-text me-2" class="text-success fw-semibold">
                                                            {{ __('crm.create_invoice_btn') }}
                                                        </x-ui.dropdown-item>
                                                    @endif
                                                    <x-ui.dropdown-item href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}" icon="feather-download me-2" target="_blank">
                                                        {{ __('crm.download_challan_pdf') }}
                                                    </x-ui.dropdown-item>
                                                </x-ui.action-dropdown>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-truck fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_dispatches_text') }}
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
                            <h6 class="mb-0 fw-bold text-dark fs-14">{{ __('crm.sales_invoices_header') }}</h6>
                        </div>
                        @if (($order->status === 'Confirmed' || $order->status === 'Partially Shipped' || $order->status === 'Shipped') && ($hasUnbilledQty ?? true) && $invoicingPolicy !== 'dispatch_order')
                            <x-ui.button href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id, 'mode' => 'sales_order']) }}" variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                {{ __('crm.create_invoice_btn') }}
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soInvoicesTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">{{ __('crm.invoice_number_col') }}</th>
                                    <th style="width: 18%;">{{ __('crm.date') }}</th>
                                    <th style="width: 25%;">{{ __('crm.source_shipment') }}</th>
                                    <th class="text-end" style="width: 17%;">{{ __('crm.grand_total') }}</th>
                                    <th style="width: 15%;">{{ __('crm.status') }}</th>
                                    <th class="text-end pe-4" style="width: 15%;">{{ __('crm.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->invoices as $inv)
                                    @php
                                        $invBadge = 'bg-soft-secondary text-secondary';
                                        if ($inv->status === 'Paid') $invBadge = 'bg-soft-success text-success';
                                        elseif ($inv->status === 'Partially Paid') $invBadge = 'bg-soft-warning text-warning';
                                        elseif ($inv->status === 'Sent' || $inv->status === 'Posted') $invBadge = 'bg-soft-info text-info';
                                        elseif ($inv->status === 'Cancelled') $invBadge = 'bg-soft-danger text-danger';
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.invoices.show', $inv->id) }}" class="text-primary">{{ $inv->invoice_number }}</a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($inv->invoice_date)) }}</td>
                                        <td>
                                            @if ($inv->dispatchOrders && $inv->dispatchOrders->isNotEmpty())
                                                <a href="{{ route('sales.dispatches.show', $inv->dispatchOrders->first()->id) }}" class="text-primary fw-semibold font-monospace">
                                                    <i class="feather-truck me-1"></i>{{ $inv->dispatchOrders->first()->dispatch_number }}
                                                </a>
                                            @elseif ($inv->materialRequirement)
                                                <a href="{{ route('inventory.material-requirements.show', $inv->material_requirement_id) }}" class="text-muted fw-semibold font-monospace">
                                                    {{ $inv->materialRequirement->requirement_number }}
                                                </a>
                                            @else
                                                <span class="text-muted fs-12">{{ __('crm.advance_so_billing') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-dark">{{ format_currency($inv->total_amount ?? $inv->grand_total) }}</td>
                                        <td>
                                            <span class="badge {{ $invBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $inv->status }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.invoices.show', $inv->id)" id="invAction-{{ $inv->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.invoices.show', $inv->id) }}" icon="feather-eye me-2">
                                                    {{ __('crm.view_invoice') }}
                                                </x-ui.dropdown-item>

                                                @if(in_array($inv->status, ['Sent', 'Partially Paid', 'Posted', 'Draft']))
                                                    <x-ui.dropdown-item href="{{ route('sales.payments.create', ['invoice_id' => $inv->id, 'customer_id' => $order->customer_id]) }}" icon="feather-dollar-sign me-2" class="text-success fw-semibold">
                                                        {{ __('crm.register_payment') }}
                                                    </x-ui.dropdown-item>
                                                @endif
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-file-text fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_invoices_text') }}
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
                            <h6 class="mb-0 fw-bold text-dark fs-14">{{ __('crm.payments_allocations_header') }}</h6>
                        </div>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                            <x-ui.button href="{{ route('sales.payments.create', ['sales_order_id' => $order->id, 'customer_id' => $order->customer_id, 'allocate_to' => 'sales_order']) }}" variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                {{ __('crm.record_receipt_advance') }}
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soPaymentsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">{{ __('crm.payment_number_col') }}</th>
                                    <th style="width: 20%;">{{ __('crm.date') }}</th>
                                    <th style="width: 20%;">{{ __('crm.method') }}</th>
                                    <th style="width: 20%;">{{ __('crm.reference_no') }}</th>
                                    <th class="text-end" style="width: 20%;">{{ __('crm.allocated_amount') }}</th>
                                    <th class="text-end pe-4" style="width: 15%;">{{ __('crm.actions') }}</th>
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
                                        <td class="text-end fw-bold text-dark">{{ format_currency($alloc->allocated_amount) }}</td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.payments.show', $alloc->payment->id)" id="payAction-{{ $alloc->payment->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.payments.show', $alloc->payment->id) }}" icon="feather-eye me-2">
                                                    {{ __('crm.view_receipt') }}
                                                </x-ui.dropdown-item>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-dollar-sign fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_payments_text') }}
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
                            <h6 class="mb-0 fw-bold text-dark fs-14">{{ __('crm.sales_returns_header') }}</h6>
                        </div>
                        @if ($order->status === 'Partially Shipped' || $order->status === 'Shipped')
                            <x-ui.button href="{{ route('sales.returns.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                {{ __('crm.create_sales_return') }}
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soReturnsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">{{ __('crm.return_number_col') }}</th>
                                    <th style="width: 20%;">{{ __('crm.date') }}</th>
                                    <th class="text-end" style="width: 20%;">{{ __('crm.refund_amount') }}</th>
                                    <th style="width: 20%;">{{ __('crm.status') }}</th>
                                    <th class="text-end pe-4" style="width: 15%;">{{ __('crm.actions') }}</th>
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
                                        <td class="text-end fw-bold text-dark">{{ format_currency($ret->total_refund_amount) }}</td>
                                        <td><span class="badge {{ $retBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $ret->status }}</span></td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.returns.show', $ret->id)" id="retAction-{{ $ret->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.returns.show', $ret->id) }}" icon="feather-eye me-2">
                                                    {{ __('crm.view_return') }}
                                                </x-ui.dropdown-item>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="feather-rotate-ccw fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_returns_text') }}
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
