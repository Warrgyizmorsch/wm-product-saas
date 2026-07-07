@extends('layouts.duralux')

@section('title', 'Delivery Order Details | SaaS ERP')
@section('page-title', 'Delivery Order ' . $delivery->delivery_number)
@section('breadcrumb', 'Sales / Deliveries / ' . $delivery->delivery_number)

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
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
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="fw-bold text-dark mb-1">Fulfillment Shipment: {{ $delivery->delivery_number }}</h4>
                    <span class="fs-12 text-muted">Related Order: 
                        <a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="fw-bold text-primary">{{ $delivery->salesOrder->sales_order_number }}</a> 
                        ({{ $delivery->salesOrder->customer?->name }})
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="btn btn-light border">
                        <i class="feather-arrow-left me-2"></i>Back to Sales Order
                    </a>

                    @if ($delivery->status === 'Draft')
                        <form action="{{ route('sales.deliveries.cancel', $delivery->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this delivery order?');">
                            @csrf
                            <button type="submit" class="btn btn-soft-danger">
                                <i class="feather-trash-2 me-2"></i>Cancel Draft
                            </button>
                        </form>

                        <button type="submit" form="shipmentAllocationForm" class="btn btn-success">
                            <i class="feather-send me-2"></i>Ship / Dispatch
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Shipment details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4.5">
                    @if ($delivery->status === 'Shipped')
                        <div class="alert alert-success border-0 bg-soft-success text-success d-flex align-items-center mb-4" style="border-radius: 4px;">
                            <i class="feather-info me-2.5 fs-15"></i>
                            <span class="fs-13 fw-semibold">This shipment has been dispatched. Stock has been deducted from warehouses and values logged in the ledger.</span>
                        </div>
                    @endif

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <div class="col-sm-6">
                            <span class="text-muted text-uppercase fs-11 fw-semibold d-block mb-1">Shipment Information</span>
                            <p class="mb-1"><strong>Shipment Date:</strong> {{ $delivery->delivery_date->format('d/m/Y') }}</p>
                            @php
                                $statusClass = 'bg-soft-secondary text-secondary';
                                if ($delivery->status === 'Shipped') $statusClass = 'bg-soft-success text-success';
                                elseif ($delivery->status === 'Cancelled') $statusClass = 'bg-soft-danger text-danger';
                            @endphp
                            <p class="mb-0"><strong>Status:</strong> <span class="badge {{ $statusClass }} px-2 py-0.5">{{ $delivery->status }}</span></p>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted text-uppercase fs-11 fw-semibold d-block mb-1">Carrier Details</span>
                            <p class="mb-1"><strong>Carrier:</strong> {{ $delivery->carrier ?: 'Not Specified' }}</p>
                            <p class="mb-0"><strong>Tracking Number:</strong> {{ $delivery->tracking_number ?: 'Not Available' }}</p>
                        </div>
                    </div>

                    @if($delivery->notes)
                        <div class="border rounded p-3 bg-light mb-4">
                            <h6 class="fw-bold text-dark fs-12 text-uppercase mb-1">Internal Notes</h6>
                            <p class="text-muted fs-12 mb-0" style="white-space: pre-line;">{{ $delivery->notes }}</p>
                        </div>
                    @endif

                    <h5 class="fw-bold text-dark mb-3 border-top pt-4 fs-14">Shipped Items Allocation</h5>

                    @if ($delivery->status === 'Draft')
                        <!-- Draft form: User must allocate batches/serials before shipping -->
                        <form action="{{ route('sales.deliveries.ship', $delivery->id) }}" method="POST" id="shipmentAllocationForm">
                            @csrf
                            <div class="table-responsive">
                                <table class="table odoo-table align-middle">
                                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                        <tr>
                                            <th>Product Details</th>
                                            <th>Warehouse</th>
                                            <th class="text-end" style="width: 10%;">Qty</th>
                                            <th>Allocation Details (Serials/Batches)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-13 text-dark">
                                        @foreach ($delivery->items as $item)
                                            <tr>
                                                <td>
                                                    <strong class="text-dark">{{ $item->product?->name }}</strong>
                                                    @if($item->product?->sku)
                                                        <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border px-2 py-0.5">{{ $item->warehouse?->name }}</span>
                                                </td>
                                                <td class="text-end fw-bold pe-3">{{ (int)$item->quantity }}</td>
                                                <td>
                                                    @php
                                                        $alloc = $itemAllocations[$item->id] ?? null;
                                                    @endphp

                                                    @if ($item->product?->track_serial_number)
                                                        <label class="fw-semibold fs-11 text-danger d-block mb-1">Select exactly {{ (int)$item->quantity }} Serial(s) *</label>
                                                        <select name="allocations[{{ $item->id }}][serials][]" class="form-select odoo-select2" multiple required style="width: 100%;">
                                                            @if($alloc && !empty($alloc['serials']))
                                                                @foreach ($alloc['serials'] as $sn)
                                                                    <option value="{{ $sn->serial_number }}">{{ $sn->serial_number }} (Cost: ₹{{ number_format($sn->purchase_rate, 2) }})</option>
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                    @elseif ($item->product?->track_batch)
                                                        <label class="fw-semibold fs-11 text-danger d-block mb-1">Select Batch *</label>
                                                        <select name="allocations[{{ $item->id }}][batch_id]" class="form-select odoo-select2" required style="width: 100%;">
                                                            <option value="">Select Batch...</option>
                                                            @if($alloc && !empty($alloc['batches']))
                                                                @foreach ($alloc['batches'] as $b)
                                                                    <option value="{{ $b->id }}">{{ $b->batch_number }} (Avail: {{ (int)$b->available_qty }}, Expiry: {{ $b->expiry_date ? $b->expiry_date->format('d/m/Y') : 'None' }})</option>
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                    @else
                                                        <span class="text-muted fs-12">Standard Lot (FIFO consumed automatically)</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    @else
                        <!-- Shipped view: Display allocated batches/serials -->
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                    <tr>
                                        <th>Product Details</th>
                                        <th>Warehouse</th>
                                        <th class="text-end" style="width: 10%;">Qty</th>
                                        <th>Allocated Tracking Details</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @foreach ($delivery->items as $item)
                                        <tr>
                                            <td>
                                                <strong class="text-dark">{{ $item->product?->name }}</strong>
                                                @if($item->product?->sku)
                                                    <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $item->warehouse?->name }}</td>
                                            <td class="text-end fw-bold">{{ (int)$item->quantity }}</td>
                                            <td>
                                                @if ($item->product?->track_serial_number)
                                                    @php
                                                        $serials = $item->serialNumbers->pluck('serial_number')->toArray();
                                                    @endphp
                                                    <span class="fw-semibold text-dark">Serials:</span>
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach ($serials as $s)
                                                            <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11 border border-primary-200">{{ $s }}</span>
                                                        @endforeach
                                                    </div>
                                                @elseif ($item->product?->track_batch)
                                                    <span class="fw-semibold text-dark">Batch Number:</span>
                                                    <span class="badge bg-soft-info text-info px-2.5 py-1 fs-11 mt-1 border border-info-200 d-inline-block">{{ $item->batch?->batch_number ?: '—' }}</span>
                                                @else
                                                    <span class="text-muted">FIFO Standard Lot</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar summary details -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-light-50">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3 fs-14">Customer Summary</h5>
                    <hr class="my-2.5">
                    <div class="fs-13 text-dark">
                        <p class="mb-2"><strong>Name:</strong> {{ $delivery->salesOrder->customer?->name }}</p>
                        <p class="mb-2"><strong>Billing Address:</strong></p>
                        <p class="text-muted fs-12 mb-3" style="white-space: pre-line;">{{ $delivery->salesOrder->billing_address ?: 'Not Specified' }}</p>
                        <p class="mb-2"><strong>Shipping Address:</strong></p>
                        <p class="text-muted fs-12 mb-0" style="white-space: pre-line;">{{ $delivery->salesOrder->shipping_address ?: 'Not Specified' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Auto-initialize dynamic select2 fields
            if ($.fn.select2) {
                $('.odoo-select2').select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });
            }
        });
    </script>
@endpush
