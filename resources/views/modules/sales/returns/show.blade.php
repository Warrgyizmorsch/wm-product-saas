@extends('layouts.duralux')

@section('title', 'Sales Return Details | SaaS ERP')
@section('page-title', 'Sales Return ' . $return->return_number)
@section('breadcrumb', 'Sales / Returns / Details')

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

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <a href="{{ route('sales.returns.index') }}" class="btn btn-sm btn-light border py-1.5 px-3">
            <i class="feather-arrow-left me-1"></i>Back to Returns
        </a>
        <div class="d-flex gap-2">
            @if ($return->status === 'Draft')
                <form action="{{ route('sales.returns.complete', $return->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success py-1.5 px-3 fw-bold">
                        <i class="feather-check me-1"></i>Complete Return (Restock Inventory)
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="erp-single-panel bg-white p-4">
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Block -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1">{{ $return->return_number }}</h4>
                    <span class="text-muted">Originating Order: <strong>{{ $return->salesOrder?->sales_order_number ?: '—' }}</strong></span>
                </div>
                <div class="text-end">
                    @if ($return->status == 'Completed')
                        <span class="badge bg-soft-success text-success px-3 py-1.5 fs-12 fw-bold">COMPLETED (RESTOCKED)</span>
                    @elseif ($return->status == 'Approved')
                        <span class="badge bg-soft-info text-info px-3 py-1.5 fs-12 fw-bold">APPROVED</span>
                    @elseif ($return->status == 'Cancelled')
                        <span class="badge bg-soft-danger text-danger px-3 py-1.5 fs-12 fw-bold">CANCELLED</span>
                    @else
                        <span class="badge bg-soft-secondary text-secondary px-3 py-1.5 fs-12 fw-bold">DRAFT</span>
                    @endif
                </div>
            </div>

            <!-- Details Block -->
            <div class="row g-4 mb-4 fs-13 text-dark">
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="text-muted d-block mb-1">Customer / Client:</span>
                        <strong class="text-dark fs-14">{{ $return->salesOrder?->customer?->name ?: '—' }}</strong>
                        <span class="d-block text-muted mt-1 fs-12">
                            {{ $return->salesOrder?->customer?->email ?: 'No Email' }} | {{ $return->salesOrder?->customer?->phone ?: 'No Phone' }}
                        </span>
                    </div>

                    @if ($return->reason)
                        <div>
                            <span class="text-muted d-block mb-1">Reason for Return:</span>
                            <p class="text-muted fs-12 bg-light p-2.5 rounded border border-light" style="white-space: pre-wrap;">{{ $return->reason }}</p>
                        </div>
                    @endif
                </div>

                <div class="col-md-6">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Return Date:</span>
                            <span class="fw-bold text-dark">{{ date('d M Y', strtotime($return->return_date)) }}</span>
                        </div>
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Total Refund Amount:</span>
                            <span class="fw-extrabold text-primary fs-14">₹{{ number_format($return->total_refund_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Return Items Table -->
            <div class="border-top pt-4 mt-4">
                <h5 class="fw-bold text-dark mb-3 fs-14">Returned Line Items</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="returnItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Product Details</th>
                                <th style="width: 30%;">Restock Warehouse</th>
                                <th class="text-end" style="width: 15%;">Quantity</th>
                                <th class="text-end pe-3" style="width: 15%;">Refund Price</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @foreach ($return->items as $item)
                                <tr>
                                    <td>
                                        <strong class="text-dark">{{ $item->product?->name }}</strong>
                                        @if($item->product?->sku)
                                            <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-muted">{{ $item->warehouse?->name ?: '—' }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-primary">{{ (int)$item->quantity }}</td>
                                    <td class="text-end pe-3 fw-bold text-dark">
                                        ₹{{ number_format($item->unit_price, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>
        </x-ui.odoo-form-ui>
    </div>
@endsection
