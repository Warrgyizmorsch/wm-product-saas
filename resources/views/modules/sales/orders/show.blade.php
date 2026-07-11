@extends('layouts.duralux')

@section('title', 'Sales Order Details | SaaS ERP')
@section('page-title', 'Sales Order ' . $order->sales_order_number)
@section('breadcrumb', 'Sales / Sales Orders / ' . $order->sales_order_number)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('sales.orders.index') }}" class="btn btn-light d-print-none">
            <i class="feather-arrow-left me-2"></i>Back to List
        </a>
        
        @if ($order->status === 'Draft')
            <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline d-print-none">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="feather-check-circle me-2"></i>Confirm Order
                </button>
            </form>
        @endif

        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
            <a href="{{ route('sales.deliveries.create', ['sales_order_id' => $order->id]) }}" class="btn btn-primary d-print-none">
                <i class="feather-truck me-2"></i>Create Delivery
            </a>
            @php
                $hasManufactureItems = $order->items->contains(fn($item) => $item->product?->supplier_method === 'manufacture');
            @endphp
            @if ($hasManufactureItems)
                <a href="{{ route('production.orders.create', ['sales_order_id' => $order->id]) }}" class="btn btn-warning d-print-none">
                    <i class="feather-cpu me-2"></i>Create MO
                </a>
            @endif
        @endif

        @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
            <form action="{{ route('sales.orders.cancel', $order->id) }}" method="POST" class="d-inline d-print-none" onsubmit="return confirm('Are you sure you want to cancel this sales order?');">
                @csrf
                <button type="submit" class="btn btn-soft-danger">
                    <i class="feather-x-circle me-2"></i>Cancel Order
                </button>
            </form>
            
            <a href="{{ route('sales.orders.edit', $order->id) }}" class="btn btn-light border d-print-none">
                <i class="feather-edit-2 me-2"></i>Edit
            </a>
        @endif

        <button onclick="window.print()" class="btn btn-outline-primary d-print-none">
            <i class="feather-printer me-2"></i>Print / Download
        </button>
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
            ['id' => 'tab-deliveries', 'label' => 'Delivery Orders (' . $order->deliveries->count() . ')', 'active' => false, 'icon' => 'feather-truck'],
            ['id' => 'tab-invoices', 'label' => 'Invoices (' . $order->invoices->count() . ')', 'active' => false, 'icon' => 'feather-file-text'],
            ['id' => 'tab-payments', 'label' => 'Payments & Advances (' . $order->allocations->count() . ')', 'active' => false, 'icon' => 'feather-dollar-sign'],
            ['id' => 'tab-returns', 'label' => 'Returns (' . $order->returns->count() . ')', 'active' => false, 'icon' => 'feather-rotate-ccw'],
            ['id' => 'tab-production', 'label' => 'Manufacturing Orders (MO) (' . ($order->productionOrders ?? collect())->count() . ')', 'active' => false, 'icon' => 'feather-cpu'],
        ];
    @endphp

    <div class="card border-0 shadow-sm print-area">
        <div class="card-header bg-white border-bottom py-0 px-4 d-print-none">
            <x-ui.horizontal-tabs id="salesOrderTabs" :tabs="$soTabs" />
        </div>
        
        <div class="card-body p-0">
            <div class="tab-content">
                <!-- TAB 1: Sales Order Details -->
                <div class="tab-pane fade show active p-5" id="tab-order">
                    <!-- Header section -->
                    <div class="row align-items-center mb-5">
                        <div class="col-sm-6 text-start">
                            <div class="d-flex align-items-center">
                                <div class="avatar-text avatar-lg bg-primary text-white fs-3 fw-bold me-3 shadow">
                                    {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                                </div>
                                <div>
                                    <h3 class="fw-bold text-dark mb-0">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h3>
                                    <p class="text-muted mb-0 fs-12">Official Sales Order Document</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-4 mt-sm-0">
                            <h4 class="fw-bold text-primary mb-1">SALES ORDER</h4>
                            <span class="fs-14 fw-bold text-dark d-block">No: {{ $order->sales_order_number }}</span>
                            @php
                                $badgeClass = 'bg-soft-secondary text-secondary';
                                if ($order->status === 'Confirmed') $badgeClass = 'bg-soft-info text-info';
                                elseif ($order->status === 'Partially Shipped') $badgeClass = 'bg-soft-warning text-warning';
                                elseif ($order->status === 'Shipped') $badgeClass = 'bg-soft-success text-success';
                                elseif ($order->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                            @endphp
                            <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 mt-1">{{ $order->status }}</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Meta details (Customer / Dates) -->
                    <div class="row mb-5">
                        <div class="col-sm-4 text-start mb-3 mb-sm-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Customer Info</span>
                            <h5 class="fw-bold text-dark mb-1">{{ $order->customer?->name ?? '—' }}</h5>
                            <p class="text-muted mb-1 fs-13"><i class="feather-mail me-2"></i>{{ $order->customer?->email ?: '—' }}</p>
                            <p class="text-muted mb-0 fs-13"><i class="feather-phone me-2"></i>{{ $order->customer?->phone ?: '—' }}</p>
                        </div>
                        <div class="col-sm-4 text-start mb-3 mb-sm-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Order Schedule</span>
                            <p class="text-dark mb-1 fs-13"><strong>Order Date:</strong> {{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</p>
                            <p class="text-dark mb-1 fs-13"><strong>Est. Shipment:</strong> {{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : 'Not Scheduled' }}</p>
                            <p class="text-dark mb-0 fs-13"><strong>Payment Terms:</strong> {{ $order->payment_terms ?: 'Due on Receipt' }}</p>
                        </div>
                        <div class="col-sm-4 text-sm-end">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Reference Details</span>
                            @if($order->quotation)
                                <p class="text-dark mb-1 fs-13"><strong>Quotation Ref:</strong> <a href="{{ route('crm.quotations.show', $order->quotation_id) }}" class="fw-bold text-primary">{{ $order->quotation->quotation_number }}</a></p>
                            @endif
                            @if($order->salesPerson)
                                <p class="text-dark mb-0 fs-13"><strong>Sales Rep:</strong> {{ $order->salesPerson->name }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Addresses grid -->
                    <div class="row g-4 mb-5 border-top border-bottom py-4 bg-light-50">
                        <div class="col-6 text-start">
                            <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2">Billing Address</h6>
                            <p class="text-muted fs-13 mb-0" style="white-space: pre-line;">{{ $order->billing_address ?: 'No billing address provided.' }}</p>
                        </div>
                        <div class="col-6 text-start">
                            <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2">Shipping Address</h6>
                            <p class="text-muted fs-13 mb-0" style="white-space: pre-line;">{{ $order->shipping_address ?: 'No shipping address provided.' }}</p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive mb-5">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-3" style="width: 5%;">#</th>
                                    <th style="width: 25%;">Product Details</th>
                                    <th style="width: 15%;">Warehouse</th>
                                    <th class="text-center" style="width: 10%;">Qty</th>
                                    <th class="text-end" style="width: 15%;">Unit Price (₹)</th>
                                    <th class="text-end" style="width: 10%;">Tax Rate</th>
                                    <th class="text-end" style="width: 10%;">Discount (₹)</th>
                                    <th class="text-end pe-3" style="width: 15%;">Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @foreach ($order->items as $index => $item)
                                    <tr>
                                        <td class="ps-3 text-muted text-center">{{ $index + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <strong class="text-dark">{{ $item->item_name }}</strong>
                                                    @if($item->product?->sku)
                                                        <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                                    @endif
                                                    @if($item->description)
                                                        <small class="text-muted d-block mt-0.5">{{ $item->description }}</small>
                                                    @endif
                                                </div>
                                                <div class="text-end">
                                                    @php
                                                        $method = $item->product?->supplier_method ?? 'buy';
                                                    @endphp
                                                    @if ($method === 'manufacture')
                                                        <span class="badge bg-soft-warning text-warning px-2 py-0.5 fs-11 fw-semibold d-inline-block">Manufacture</span>
                                                        @php
                                                            $linkedMo = $order->productionOrders->firstWhere('sales_order_item_id', $item->id);
                                                        @endphp
                                                        @if ($linkedMo)
                                                            <div class="mt-1">
                                                                <a href="{{ route('production.orders.show', $linkedMo->id) }}" class="text-primary fw-bold fs-11">
                                                                    <i class="feather-cpu me-1"></i>{{ $linkedMo->order_number }}
                                                                </a>
                                                                <span class="badge bg-soft-secondary text-secondary px-1 py-0.2 fs-10">{{ ucfirst($linkedMo->status) }}</span>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11 fw-semibold d-inline-block">Buy</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {{ $item->warehouse?->name ?: '—' }}
                                        </td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end">{{ number_format($item->tax_rate, 2) }}%</td>
                                        <td class="text-end">
                                            @if($item->discount > 0)
                                                ₹{{ number_format($item->discount, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 fw-semibold">₹{{ number_format($item->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals & Calculations -->
                    <div class="row g-4">
                        <div class="col-sm-7 text-start">
                            @if($order->terms_conditions)
                                <h6 class="fw-bold text-dark mb-2 fs-12 text-uppercase">Terms & Conditions</h6>
                                <p class="text-muted fs-12 mb-4" style="white-space: pre-line;">{{ $order->terms_conditions }}</p>
                            @endif

                            @if($order->notes)
                                <h6 class="fw-bold text-dark mb-2 fs-12 text-uppercase">Internal Notes / Remarks</h6>
                                <p class="text-muted fs-12 mb-0" style="white-space: pre-line;">{{ $order->notes }}</p>
                            @endif
                        </div>
                        <div class="col-sm-5">
                            <div class="border p-3 rounded bg-light">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-bold text-dark">₹{{ number_format($order->subtotal, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Tax total (GST):</span>
                                    <span class="fw-bold text-dark">₹{{ number_format($order->tax, 2) }}</span>
                                </div>
                                @if($order->discount > 0)
                                    <div class="d-flex justify-content-between mb-2 text-danger">
                                        <span>Discount:</span>
                                        <span>-₹{{ number_format($order->discount, 2) }}</span>
                                    </div>
                                @endif
                                @if($order->shipping_charges > 0)
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Shipping Charges:</span>
                                        <span class="fw-bold text-dark">₹{{ number_format($order->shipping_charges, 2) }}</span>
                                    </div>
                                @endif
                                @if($order->adjustment != 0)
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Adjustment:</span>
                                        <span class="fw-bold text-dark">₹{{ number_format($order->adjustment, 2) }}</span>
                                    </div>
                                @endif
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-15 fw-bold text-dark">Total Amount:</span>
                                    <span class="fs-15 fw-bold text-primary">₹{{ number_format($order->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signature block -->
                    <div class="row mt-5 pt-4">
                        <div class="col-6 text-start">
                            <p class="fs-11 text-muted mb-0">For queries regarding fulfillment, please refer to the sales department.</p>
                        </div>
                        <div class="col-6 text-end">
                            <div class="d-inline-block text-center" style="width: 200px;">
                                <hr class="mb-1 mt-5">
                                <span class="fs-11 text-muted text-uppercase fw-semibold">Authorized Signature</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Delivery Orders -->
                <div class="tab-pane fade" id="tab-deliveries">
                    <div class="d-flex justify-content-between align-items-center py-3 px-4 border-bottom bg-light bg-opacity-10">
                        <h5 class="mb-0 fw-bold text-dark fs-14"><i class="feather-truck me-2 text-primary"></i>Fulfillment History (Delivery Orders)</h5>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                            <x-ui.button href="{{ route('sales.deliveries.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus">
                                Create Delivery Order
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Delivery Number</th>
                                    <th>Date</th>
                                    <th>Carrier</th>
                                    <th>Tracking Number</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->deliveries as $do)
                                    @php
                                        $doBadge = 'bg-soft-secondary text-secondary';
                                        if ($do->status === 'Shipped') $doBadge = 'bg-soft-success text-success';
                                        elseif ($do->status === 'Cancelled') $doBadge = 'bg-soft-danger text-danger';

                                        // Check if this specific shipment has already been invoiced
                                        $invoiced = $order->invoices->where('delivery_order_id', $do->id)->first();
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold"><a href="{{ route('sales.deliveries.show', $do->id) }}" class="text-primary">{{ $do->delivery_number }}</a></td>
                                        <td>{{ $do->delivery_date->format('d/m/Y') }}</td>
                                        <td>{{ $do->carrier ?: '—' }}</td>
                                        <td>{{ $do->tracking_number ?: '—' }}</td>
                                        <td><span class="badge {{ $doBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $do->status }}</span></td>
                                        <td class="text-end pe-4">
                                            @if ($do->status === 'Shipped' && $invoiced)
                                                <div class="d-flex justify-content-end align-items-center gap-2">
                                                    <span class="fs-12 text-muted me-2">
                                                        Invoiced: <a href="{{ route('sales.invoices.show', $invoiced->id) }}" class="text-success fw-bold">{{ $invoiced->invoice_number }}</a>
                                                    </span>
                                                    <x-ui.action-dropdown :viewUrl="route('sales.deliveries.show', $do->id)">
                                                        <x-ui.dropdown-item href="{{ route('sales.deliveries.show', $do->id) }}" icon="feather-eye">
                                                            View Details
                                                        </x-ui.dropdown-item>
                                                    </x-ui.action-dropdown>
                                                </div>
                                            @else
                                                <x-ui.action-dropdown :viewUrl="route('sales.deliveries.show', $do->id)">
                                                    <x-ui.dropdown-item href="{{ route('sales.deliveries.show', $do->id) }}" icon="feather-eye">
                                                        View Details
                                                    </x-ui.dropdown-item>
                                                    @php
                                                        $invoicePolicy = config('sales.invoice_policy', 'On Dispatch');
                                                        $canInvoice = ($invoicePolicy === 'On Dispatch') 
                                                            ? in_array($do->status, ['Dispatched', 'Delivered', 'Shipped']) 
                                                            : ($do->status === 'Delivered');
                                                    @endphp
                                                    @if ($canInvoice && !$invoiced)
                                                        <x-ui.dropdown-item href="{{ route('sales.invoices.create', ['delivery_order_id' => $do->id]) }}" icon="feather-file-text">
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
                                            @if ($inv->deliveryOrder)
                                                <a href="{{ route('sales.deliveries.show', $inv->delivery_order_id) }}" class="text-muted fw-semibold">
                                                    {{ $inv->deliveryOrder->delivery_number }}
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

                <!-- TAB 6: Manufacturing Orders (MO) -->
                <div class="tab-pane fade" id="tab-production">
                    <div class="d-flex justify-content-between align-items-center py-3 px-4 border-bottom bg-light bg-opacity-10">
                        <h5 class="mb-0 fw-bold text-dark fs-14"><i class="feather-cpu me-2 text-primary"></i>Manufacturing Orders (MO)</h5>
                        @php
                            $hasManufactureItems = $order->items->contains(fn($item) => $item->product?->supplier_method === 'manufacture');
                        @endphp
                        @if (($order->status === 'Confirmed' || $order->status === 'Partially Shipped') && $hasManufactureItems)
                            <x-ui.button href="{{ route('production.orders.create', ['sales_order_id' => $order->id]) }}" variant="primary" size="sm" icon="feather-plus">
                                Create MO
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">MO Number</th>
                                    <th>Target Product</th>
                                    <th class="text-end">Qty Ordered</th>
                                    <th class="text-end">Qty Produced</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->productionOrders ?? [] as $mo)
                                    @php
                                        $moBadge = 'bg-soft-secondary text-secondary';
                                        if ($mo->status === 'completed') $moBadge = 'bg-soft-success text-success';
                                        elseif ($mo->status === 'released') $moBadge = 'bg-soft-info text-info';
                                        elseif ($mo->status === 'in_progress') $moBadge = 'bg-soft-warning text-warning';
                                        elseif ($mo->status === 'cancelled') $moBadge = 'bg-soft-danger text-danger';
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('production.orders.show', $mo->id) }}" class="text-primary">{{ $mo->order_number }}</a>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ $mo->product?->name }}</span>
                                            @if ($mo->product?->sku)
                                                <small class="text-muted d-block">SKU: {{ $mo->product->sku }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ (int)$mo->quantity_ordered }}</td>
                                        <td class="text-end text-muted">{{ (int)$mo->quantity_produced }}</td>
                                        <td>{{ $mo->start_date ? $mo->start_date->format('d/m/Y') : '—' }}</td>
                                        <td>{{ $mo->end_date ? $mo->end_date->format('d/m/Y') : '—' }}</td>
                                        <td>
                                            <span class="badge {{ $moBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ ucfirst($mo->status) }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <x-ui.button href="{{ route('production.orders.show', $mo->id) }}" variant="outline-primary" size="sm" class="fw-bold">
                                                View MO
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="feather-cpu fs-1 mb-2 d-block text-gray-300"></i>
                                            No manufacturing orders created for this Sales Order yet.
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
