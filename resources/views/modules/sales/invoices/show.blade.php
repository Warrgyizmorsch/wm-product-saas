@extends('layouts.duralux')

@section('title', 'Invoice Details | SaaS ERP')
@section('page-title', 'Invoice ' . $invoice->invoice_number)
@section('breadcrumb', 'Sales / Invoices / Details')

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

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <a href="{{ route('sales.invoices.index') }}" class="btn btn-sm btn-light border py-1.5 px-3">
            <i class="feather-arrow-left me-1"></i>Back to Invoices
        </a>
        <div class="d-flex gap-2">
            @if ($invoice->status === 'Draft')
                <form action="{{ route('sales.invoices.send', $invoice->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary py-1.5 px-3">
                        <i class="feather-mail me-1"></i>Mark as Sent
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
                    <h4 class="fw-bold text-dark mb-1">{{ $invoice->invoice_number }}</h4>
                    <span class="text-muted">Originating Order: <strong>{{ $invoice->salesOrder->sales_order_number }}</strong></span>
                    @if ($invoice->materialRequirement)
                        <span class="text-muted d-block mt-1">Originating Requirement: <a href="{{ route('sales.material-requirements.show', $invoice->material_requirement_id) }}" class="badge bg-soft-success text-success fw-bold">{{ $invoice->materialRequirement->requirement_number }}</a></span>
                    @endif
                </div>
                <div class="text-end">
                    @if ($invoice->status == 'Paid')
                        <span class="badge bg-soft-success text-success px-3 py-1.5 fs-12 fw-bold">PAID</span>
                    @elseif ($invoice->status == 'Partially Paid')
                        <span class="badge bg-soft-warning text-warning px-3 py-1.5 fs-12 fw-bold">PARTIALLY PAID</span>
                    @elseif ($invoice->status == 'Sent')
                        <span class="badge bg-soft-info text-info px-3 py-1.5 fs-12 fw-bold">SENT</span>
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
                        <strong class="text-dark fs-14">{{ $invoice->salesOrder->customer?->name }}</strong>
                        <span class="d-block text-muted mt-1 fs-12">
                            {{ $invoice->salesOrder->customer?->email ?: 'No Email' }} | {{ $invoice->salesOrder->customer?->phone ?: 'No Phone' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-muted d-block mb-1 font-weight-semibold">Billing Address:</span>
                        <p class="text-muted fs-12 bg-light p-2.5 rounded border border-light" style="white-space: pre-wrap;">{{ $invoice->salesOrder->billing_address ?: 'No Billing Address' }}</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Invoice Date:</span>
                            <span class="fw-bold text-dark">{{ date('d M Y', strtotime($invoice->invoice_date)) }}</span>
                        </div>
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Due Date:</span>
                            <span class="fw-bold text-dark">{{ $invoice->due_date ? date('d M Y', strtotime($invoice->due_date)) : '—' }}</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Payment Terms:</span>
                            <span class="fw-semibold text-muted">{{ $invoice->salesOrder->payment_terms ?: '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Items Table -->
            <div class="border-top pt-4 mt-4">
                <h5 class="fw-bold text-dark mb-3 fs-14">Items Billed</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="invoiceItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Product Details</th>
                                <th style="width: 25%;">Warehouse</th>
                                <th class="text-end" style="width: 10%;">Qty</th>
                                <th class="text-end" style="width: 13%;">Unit Price</th>
                                <th class="text-end" style="width: 10%;">Tax Rate</th>
                                <th class="text-end pe-3" style="width: 14%;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>
                                        <strong class="text-dark">{{ $item->product?->name ?: $item->sales_order_item_id }}</strong>
                                        @if($item->product?->sku)
                                            <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-muted">{{ $item->warehouse?->name ?: '—' }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{{ (int)$item->quantity }}</td>
                                    <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-end text-muted">{{ (int)$item->tax_rate }}%</td>
                                    <td class="text-end pe-3 fw-bold text-dark">
                                        ₹{{ number_format($item->subtotal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Calculation Grid -->
            <div class="row mt-4 pt-3 border-top justify-content-end fs-13">
                <!-- Payment Allocations Applied -->
                <div class="col-md-7">
                    @if ($invoice->allocations->count() > 0)
                        <h6 class="fw-bold text-info mb-2 fs-13"><i class="feather-dollar-sign me-1"></i>Applied Payments & Advances</h6>
                        <div class="table-responsive">
                            <table class="table table-sm border-0 align-middle mb-0 text-muted">
                                <thead>
                                    <tr class="border-bottom text-uppercase fs-10 fw-bold">
                                        <th class="ps-0">Payment No</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end pe-0">Allocated Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->allocations as $alloc)
                                        <tr>
                                            <td class="ps-0 font-weight-bold">
                                                <a href="{{ route('sales.payments.show', $alloc->payment->id) }}" class="text-info fw-bold">
                                                    {{ $alloc->payment->payment_number }}
                                                </a>
                                            </td>
                                            <td>{{ date('d/m/Y', strtotime($alloc->payment->payment_date)) }}</td>
                                            <td>{{ $alloc->payment->payment_method }}</td>
                                            <td class="text-end pe-0 fw-semibold text-dark">₹{{ number_format($alloc->allocated_amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <span class="text-muted d-block py-2 fs-12 border rounded bg-light px-3"><i class="feather-info me-1"></i>No payments applied yet. Go to Payments to record customer receipts.</span>
                    @endif
                </div>

                <!-- Invoice Summary -->
                <div class="col-md-5">
                    <div class="bg-light p-3 rounded">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold text-dark">₹{{ number_format($invoice->subtotal, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Discount:</span>
                            <span class="fw-bold text-danger">-₹{{ number_format($invoice->discount, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Tax total:</span>
                            <span class="fw-bold text-dark">₹{{ number_format($invoice->tax_total, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Grand Total:</span>
                            <span class="fw-extrabold text-primary fs-14">₹{{ number_format($invoice->grand_total, 2) }}</span>
                        </div>
                        @if ($adjustedAmount > 0)
                            <div class="d-flex justify-content-between py-1 border-bottom text-info">
                                <span>Credits / Advances Applied:</span>
                                <span class="fw-bold">-₹{{ number_format($adjustedAmount, 2) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between py-1.5 mt-1 bg-white px-2 rounded">
                            <span class="fw-bold text-dark">Balance Due:</span>
                            <span class="fw-extrabold {{ $balanceDue > 0 ? 'text-danger' : 'text-success' }} fs-14">₹{{ number_format($balanceDue, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if ($invoice->notes)
                <div class="row g-4 mt-2 border-top pt-3 fs-12 text-muted">
                    <div class="col-md-12">
                        <strong class="text-dark d-block mb-1">Invoice Notes:</strong>
                        <p class="mb-0 bg-light p-2 rounded">{{ $invoice->notes }}</p>
                    </div>
                </div>
            @endif
        </x-ui.odoo-form-ui>
    </div>
@endsection
