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

        @if ($order->status === 'Confirmed')
            <form action="{{ route('sales.orders.ship', $order->id) }}" method="POST" class="d-inline d-print-none">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="feather-truck me-2"></i>Mark as Shipped
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

    <div class="card border-0 shadow-sm print-area">
        <div class="card-body p-5">
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
                            <th style="width: 35%;">Product Details</th>
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
                                    <strong class="text-dark">{{ $item->item_name }}</strong>
                                    @if($item->product?->sku)
                                        <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                    @endif
                                    @if($item->description)
                                        <small class="text-muted d-block mt-0.5">{{ $item->description }}</small>
                                    @endif
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

            .card-body.p-5 {
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
