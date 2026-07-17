@extends('layouts.duralux')

@section('title', 'Sales Order Details | SaaS ERP')
@section('page-title', 'Sales Order ' . $order->sales_order_number)
@section('breadcrumb', 'Sales / Sales Orders / ' . $order->sales_order_number)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('sales.orders.index') }}" class="btn d-print-none d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0;" data-bs-toggle="tooltip" title="Back to List">
            <i class="feather-arrow-left text-dark fs-16"></i>
        </a>
        
        @if ($order->status === 'Draft')
            <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline d-print-none">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="feather-check-circle me-2"></i>Confirm Order
                </button>
            </form>
        @endif

        @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
            <form action="{{ route('sales.orders.cancel', $order->id) }}" method="POST" class="d-inline d-print-none" onsubmit="return confirm('Are you sure you want to cancel this sales order?');">
                @csrf
                <button type="submit" class="btn btn-soft-danger">
                    <i class="feather-x-circle me-2"></i>Cancel Order
                </button>
            </form>
            
            <a href="{{ route('sales.orders.edit', $order->id) }}" class="btn d-print-none d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0;" data-bs-toggle="tooltip" title="Edit Sales Order">
                <i class="feather-edit-2 text-dark fs-16"></i>
            </a>
        @endif

        <a href="{{ route('sales.orders.download', $order->id) }}" class="btn d-print-none d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0;" data-bs-toggle="tooltip" title="Print / Download PDF">
            <i class="feather-printer text-dark fs-16"></i>
        </a>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 d-print-none" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4 d-print-none" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <ul class="fs-12 mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $soTabs = [
            ['id' => 'tab-order', 'label' => 'Sales Order Details', 'active' => true, 'icon' => 'feather-shopping-cart'],
            ['id' => 'tab-deliveries', 'label' => 'Material Requirements (' . $order->materialRequirements->count() . ')', 'active' => false, 'icon' => 'feather-clipboard'],
            ['id' => 'tab-invoices', 'label' => 'Invoices (' . $order->invoices->count() . ')', 'active' => false, 'icon' => 'feather-file-text'],
            ['id' => 'tab-payments', 'label' => 'Payments (' . $order->allocations->count() . ')', 'active' => false, 'icon' => 'feather-dollar-sign'],
            ['id' => 'tab-returns', 'label' => 'Returns (' . $order->returns->count() . ')', 'active' => false, 'icon' => 'feather-rotate-ccw'],
        ];
    @endphp

@once
    @push('styles')
        <style>
            .so-status-pipeline {
                display: inline-flex;
                align-items: center;
                border-radius: 4px;
                overflow: hidden;
                border: 1px solid #cbd5e1;
                background-color: #f1f5f9;
            }
            .so-status-pipeline .pipeline-step {
                position: relative;
                padding: 6px 14px 6px 24px;
                background-color: #f1f5f9;
                color: #64748b;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border: none;
                outline: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
            }
            .so-status-pipeline .pipeline-step:first-child {
                padding-left: 14px;
                border-top-left-radius: 3px;
                border-bottom-left-radius: 3px;
            }
            .so-status-pipeline .pipeline-step:last-child {
                padding-right: 14px;
                border-top-right-radius: 3px;
                border-bottom-right-radius: 3px;
            }
            /* Right pointing arrowhead */
            .so-status-pipeline .pipeline-step::after {
                content: "";
                position: absolute;
                top: 0;
                right: -10px;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #f1f5f9;
                z-index: 10;
                transition: all 0.2s ease;
            }
            /* Left cutout */
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
            /* Active stage */
            .so-status-pipeline .pipeline-step.active {
                background-color: #3454d1;
                color: #ffffff;
            }
            .so-status-pipeline .pipeline-step.active::after {
                border-left-color: #3454d1;
            }
            /* Completed/previous stage */
            .so-status-pipeline .pipeline-step.completed {
                background-color: #cbd5e1;
                color: #475569;
            }
            .so-status-pipeline .pipeline-step.completed::after {
                border-left-color: #cbd5e1;
            }
            /* Scrollable tabs overrides */
            #salesOrderTabs {
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE 10+ */
            }
            #salesOrderTabs::-webkit-scrollbar {
                display: none; /* Safari and Chrome */
            }
            #salesOrderTabs .nav-item {
                flex-shrink: 0 !important;
            }
        </style>
    @endpush
@endonce

    <div class="card border-0 shadow-sm print-area">
        <div class="card-header bg-white border-bottom py-0 px-4 d-print-none d-flex justify-content-between align-items-center flex-wrap gap-2" style="min-height: 48px;">
            <div class="d-flex align-items-center" style="max-width: 100%; overflow: hidden;">
                <x-ui.horizontal-tabs id="salesOrderTabs" :tabs="$soTabs" class="border-0 mb-0" />
            </div>
            
            <!-- Custom Chevron Status Pipeline -->
            <div class="so-status-pipeline my-2 d-print-none" style="margin-left: auto;">
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
                                    <th class="py-2 ps-3" style="width: 38%;">Product Details</th>
                                    <th class="py-2 text-center" style="width: 15%;">Warehouse</th>
                                    <th class="text-center py-2" style="width: 7%;">Qty</th>
                                    <th class="text-end py-2 pe-3" style="width: 11%;">Unit Price</th>
                                    <th class="text-end py-2 pe-3" style="width: 9%;">Tax Rate</th>
                                    <th class="text-end py-2 pe-3" style="width: 8%;">Discount</th>
                                    <th class="text-end pe-4 py-2" style="width: 13%;">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12 text-dark">
                                @foreach ($order->items as $index => $item)
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
                                                        <span class="badge bg-soft-success text-success px-1.5 py-0.2 fs-9 fw-semibold rounded-pill">Buy</span>
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
                                        <td class="text-end text-muted py-1.5 pe-3">{{ number_format($item->tax_rate, 2) }}%</td>
                                        <td class="text-end text-muted py-1.5 pe-3">
                                            @if($item->discount > 0)
                                                ₹{{ number_format($item->discount, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-dark py-1.5">₹{{ number_format($item->amount, 2) }}</td>
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
                        <div class="col-sm-5">
                            <div class="border p-3 rounded bg-light">
                                <div class="d-flex justify-content-between mb-1.5 fs-12">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold text-dark">₹{{ number_format($order->subtotal, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1.5 fs-12">
                                    <span class="text-muted">Tax total (GST):</span>
                                    <span class="fw-semibold text-dark">₹{{ number_format($order->tax, 2) }}</span>
                                </div>
                                @if($order->discount > 0)
                                    <div class="d-flex justify-content-between mb-1.5 fs-12 text-danger">
                                        <span>Discount:</span>
                                        <span>-₹{{ number_format($order->discount, 2) }}</span>
                                    </div>
                                @endif
                                @if($order->shipping_charges > 0)
                                    <div class="d-flex justify-content-between mb-1.5 fs-12">
                                        <span class="text-muted">Shipping Charges:</span>
                                        <span class="fw-semibold text-dark">₹{{ number_format($order->shipping_charges, 2) }}</span>
                                    </div>
                                @endif
                                @if($order->adjustment != 0)
                                    <div class="d-flex justify-content-between mb-1.5 fs-12">
                                        <span class="text-muted">Adjustment:</span>
                                        <span class="fw-semibold text-dark">₹{{ number_format($order->adjustment, 2) }}</span>
                                    </div>
                                @endif
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-13 fw-bold text-dark">Total Amount:</span>
                                    <span class="fs-13 fw-bold text-primary">₹{{ number_format($order->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signature block -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-6 text-start">
                            <p class="fs-10 text-muted mb-0">For queries regarding fulfillment, please refer to the sales department.</p>
                        </div>
                        <div class="col-6 text-end">
                            <div class="d-inline-block text-center" style="width: 180px;">
                                <hr class="mb-1 mt-3">
                                <span class="fs-10 text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Authorized Signature</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Material Requirements -->
                <div class="tab-pane fade" id="tab-deliveries">
                    <div class="d-flex justify-content-between align-items-center py-3 px-4 border-bottom bg-light bg-opacity-10">
                        <h5 class="mb-0 fw-bold text-dark fs-14"><i class="feather-clipboard me-2 text-primary"></i>Fulfillment History (Material Requirements)</h5>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                            <x-ui.button href="{{ route('sales.material-requirements.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus">
                                Create Material Requirement
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Requirement Number</th>
                                    <th>Date</th>
                                    <th>Carrier</th>
                                    <th>Tracking Number</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->materialRequirements as $do)
                                    @php
                                        $doBadge = 'bg-soft-secondary text-secondary';
                                        if ($do->status === 'Shipped') $doBadge = 'bg-soft-success text-success';
                                        elseif ($do->status === 'Cancelled') $doBadge = 'bg-soft-danger text-danger';

                                        // Check if this specific shipment has already been invoiced
                                        $invoiced = $order->invoices->where('material_requirement_id', $do->id)->first();
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold"><a href="{{ route('sales.material-requirements.show', $do->id) }}" class="text-primary">{{ $do->requirement_number }}</a></td>
                                        <td>{{ $do->requirement_date->format('d/m/Y') }}</td>
                                        <td>{{ $do->carrier ?: '—' }}</td>
                                        <td>{{ $do->tracking_number ?: '—' }}</td>
                                        <td><span class="badge {{ $doBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $do->status }}</span></td>
                                        <td class="text-end pe-4">
                                            @if ($do->status === 'Shipped' && $invoiced)
                                                <div class="d-flex justify-content-end align-items-center gap-2">
                                                    <span class="fs-12 text-muted me-2">
                                                        Invoiced: <a href="{{ route('sales.invoices.show', $invoiced->id) }}" class="text-success fw-bold">{{ $invoiced->invoice_number }}</a>
                                                    </span>
                                                    <x-ui.action-dropdown :viewUrl="route('sales.material-requirements.show', $do->id)">
                                                        <x-ui.dropdown-item href="{{ route('sales.material-requirements.show', $do->id) }}" icon="feather-eye">
                                                            View Details
                                                        </x-ui.dropdown-item>
                                                    </x-ui.action-dropdown>
                                                </div>
                                            @else
                                                <x-ui.action-dropdown :viewUrl="route('sales.material-requirements.show', $do->id)">
                                                    <x-ui.dropdown-item href="{{ route('sales.material-requirements.show', $do->id) }}" icon="feather-eye">
                                                        View Details
                                                    </x-ui.dropdown-item>
                                                    @php
                                                        $invoicePolicy = config('sales.invoice_policy', 'On Dispatch');
                                                        $canInvoice = ($invoicePolicy === 'On Dispatch') 
                                                            ? in_array($do->status, ['Dispatched', 'Delivered', 'Shipped']) 
                                                            : ($do->status === 'Delivered');
                                                    @endphp
                                                    @if ($canInvoice && !$invoiced)
                                                        <x-ui.dropdown-item href="{{ route('sales.invoices.create', ['material_requirement_id' => $do->id]) }}" icon="feather-file-text">
                                                            Create Invoice
                                                        </x-ui.dropdown-item>
                                                    @endif
                                                </x-ui.action-dropdown>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-truck fs-1 mb-2 d-block text-gray-300"></i>
                                            No delivery orders created for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: Invoices -->
                <div class="tab-pane fade" id="tab-invoices">
                    <div class="d-flex justify-content-between align-items-center py-3 px-4 border-bottom bg-light bg-opacity-10">
                        <h5 class="mb-0 fw-bold text-dark fs-14"><i class="feather-file-text me-2 text-primary"></i>Sales Invoices</h5>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped' || $order->status === 'Shipped')
                            <x-ui.button href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus">
                                Create Invoice
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Invoice Number</th>
                                    <th>Date</th>
                                    <th>Source Shipment</th>
                                    <th class="text-end">Grand Total</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
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
                                        <td class="ps-4 fw-bold"><a href="{{ route('sales.invoices.show', $inv->id) }}" class="text-primary">{{ $inv->invoice_number }}</a></td>
                                        <td>{{ date('d/m/Y', strtotime($inv->invoice_date)) }}</td>
                                        <td>
                                            @if ($inv->materialRequirement)
                                                <a href="{{ route('sales.material-requirements.show', $inv->material_requirement_id) }}" class="text-muted fw-semibold">
                                                    {{ $inv->materialRequirement->requirement_number }}
                                                </a>
                                            @else
                                                <span class="text-muted">Full Order Billing</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold">₹{{ number_format($inv->grand_total, 2) }}</td>
                                        <td><span class="badge {{ $invBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $inv->status }}</span></td>
                                        <td class="text-end pe-4">
                                            <x-ui.button href="{{ route('sales.invoices.show', $inv->id) }}" variant="outline-primary" size="sm" class="fw-bold">
                                                View Invoice
                                            </x-ui.button>
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
                        </table>
                    </div>
                </div>

                <!-- TAB 4: Payments & Advance Allocations -->
                <div class="tab-pane fade" id="tab-payments">
                    <div class="d-flex justify-content-between align-items-center py-3 px-4 border-bottom bg-light bg-opacity-10">
                        <h5 class="mb-0 fw-bold text-dark fs-14"><i class="feather-dollar-sign me-2 text-primary"></i>Payments & Advance Allocations</h5>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                            <x-ui.button href="{{ route('sales.payments.create', ['sales_order_id' => $order->id, 'customer_id' => $order->customer_id, 'allocate_to' => 'sales_order']) }}" variant="primary" size="sm" icon="feather-plus">
                                Record Receipt / Advance
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Payment Number</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Reference No</th>
                                    <th class="text-end pe-4">Allocated Amount</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->allocations as $alloc)
                                    <tr>
                                        <td class="ps-4 fw-bold"><a href="{{ route('sales.payments.show', $alloc->payment->id) }}" class="text-primary">{{ $alloc->payment->payment_number }}</a></td>
                                        <td>{{ date('d/m/Y', strtotime($alloc->payment->payment_date)) }}</td>
                                        <td>{{ $alloc->payment->payment_method }}</td>
                                        <td class="text-muted">{{ $alloc->payment->reference_no ?: '—' }}</td>
                                        <td class="text-end pe-4 fw-bold text-dark">₹{{ number_format($alloc->allocated_amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="feather-dollar-sign fs-1 mb-2 d-block text-gray-300"></i>
                                            No payment receipts or advances adjusted for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 5: Returns -->
                <div class="tab-pane fade" id="tab-returns">
                    <div class="d-flex justify-content-between align-items-center py-3 px-4 border-bottom bg-light bg-opacity-10">
                        <h5 class="mb-0 fw-bold text-dark fs-14"><i class="feather-rotate-ccw me-2 text-primary"></i>Sales Returns</h5>
                        @if ($order->status === 'Partially Shipped' || $order->status === 'Shipped')
                            <x-ui.button href="{{ route('sales.returns.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus">
                                Create Sales Return
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Return Number</th>
                                    <th>Date</th>
                                    <th class="text-end">Refund Amount</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
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
                                        <td class="ps-4 fw-bold"><a href="{{ route('sales.returns.show', $ret->id) }}" class="text-primary">{{ $ret->return_number }}</a></td>
                                        <td>{{ date('d/m/Y', strtotime($ret->return_date)) }}</td>
                                        <td class="text-end fw-bold">₹{{ number_format($ret->total_refund_amount, 2) }}</td>
                                        <td><span class="badge {{ $retBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $ret->status }}</span></td>
                                        <td class="text-end pe-4">
                                            <x-ui.button href="{{ route('sales.returns.show', $ret->id) }}" variant="outline-primary" size="sm" class="fw-bold">
                                                View Return
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="feather-rotate-ccw fs-1 mb-2 d-block text-gray-300"></i>
                                            No returns processed for this Sales Order yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
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
