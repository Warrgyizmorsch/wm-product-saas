@extends('layouts.duralux')

@section('title', 'Voucher ' . $voucher->voucher_number . ' | SaaS ERP')
@section('page-title', 'Landed Cost Voucher Details')
@section('breadcrumb')
    <a href="{{ route('purchase.landed-costs.index') }}">Landed Cost Vouchers</a> &gt; Details
@endsection

@push('styles')
    <style>
        .odoo-sheet {
            background: #ffffff;
            border-radius: 4px;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark odoo-sheet">
        <!-- Title & Ribbon Bar Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div>
                <small class="text-muted text-uppercase font-monospace fw-bold fs-11">Landed Cost Voucher</small>
                <h3 class="fw-bold text-dark mb-0 font-monospace">{{ $voucher->voucher_number }}</h3>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <small class="text-muted text-uppercase font-monospace fw-bold fs-11 d-block">Total Additional Expenses</small>
                    <h3 class="fw-bold text-primary font-monospace mb-0">₹{{ number_format($voucher->total_expenses, 2) }}</h3>
                </div>

                @if($voucher->isDraft())
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('purchase.landed-costs.post', $voucher->id) }}" class="d-inline">
                            @csrf
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check-circle">
                                Post Voucher &amp; Update Stock Valuation
                            </x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('purchase.landed-costs.destroy', $voucher->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this voucher?');">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="light" size="sm" class="text-danger border">
                                Cancel Voucher
                            </x-ui.button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <!-- Primary 2-Column Fields Grid -->
        <div class="row g-4 mb-4 fs-13">
            <div class="col-md-6 border-end pe-md-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>Voucher Details</h6>

                <x-ui.odoo-form-ui type="input" label="Voucher Date" name="voucher_date_dummy" value="{{ date('d-M-Y', strtotime($voucher->voucher_date)) }}" readonly="true" />

                <div class="odoo-form-group">
                    <label class="odoo-form-label">Linked GRN(s)</label>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-1 pt-1">
                            @foreach($voucher->receipts as $receipt)
                                @if($receipt->goodsReceiptNote)
                                    <a href="{{ route('grns.show', $receipt->goods_receipt_note_id) }}" class="badge bg-light text-primary border px-2.5 py-1.5 font-monospace text-decoration-none">
                                        <i class="feather-truck me-1"></i>{{ $receipt->goodsReceiptNote->grn_number }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 ps-md-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>Status &amp; Posting</h6>

                <x-ui.odoo-form-ui type="input" label="Status" name="status_dummy" value="{{ ucfirst($voucher->status) }}" readonly="true" class="fw-bold" />

                <x-ui.odoo-form-ui type="input" label="Posting Date" name="posting_date_dummy" value="{{ $voucher->posting_date ? date('d-M-Y', strtotime($voucher->posting_date)) : '—' }}" readonly="true" />
            </div>
        </div>

        <!-- Section 1: Expenses Breakdown Table -->
        <div class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-primary mb-3"><i class="feather-layers me-2"></i>1. Additional Procurement Expenses</h6>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 25%">Expense Head</th>
                            <th style="width: 30%">Vendor / Supplier</th>
                            <th style="width: 25%">Allocation Basis</th>
                            <th style="width: 20%" class="text-end">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($voucher->expenses as $exp)
                            <tr>
                                <td class="fw-bold text-dark">{{ $exp->cost_head }}</td>
                                <td>{{ $exp->vendor->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 fs-11">
                                        @if($exp->allocation_basis === 'by_amount')
                                            By Item Base Amount
                                        @elseif($exp->allocation_basis === 'equal')
                                            Equal Distribution
                                        @else
                                            By Quantity / Weight
                                        @endif
                                    </span>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">₹{{ number_format($exp->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Section 2: Item Landed Cost Valuation Breakdown -->
        <div class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>2. Item Landed Cost Valuation Breakdown</h6>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 15%">GRN #</th>
                            <th style="width: 25%">Product Details</th>
                            <th style="width: 12%" class="text-center">Quantity</th>
                            <th style="width: 13%" class="text-end">Base Unit Rate</th>
                            <th style="width: 15%" class="text-end">Allocated Extra Cost</th>
                            <th style="width: 20%" class="text-end">New Landed Unit Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($voucher->items as $item)
                            <tr>
                                <td>
                                    <span class="font-monospace fw-bold text-primary">
                                        {{ $item->goodsReceiptNote->grn_number ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $item->product->name ?? '—' }}</div>
                                    <small class="text-muted font-monospace">SKU: {{ $item->product->sku ?? '—' }}</small>
                                </td>
                                <td class="text-center fw-semibold">{{ (float)$item->quantity }} {{ $item->product->uom->code ?? 'PCS' }}</td>
                                <td class="text-end font-monospace">₹{{ number_format($item->base_unit_rate, 2) }}</td>
                                <td class="text-end font-monospace text-primary fw-bold">+ ₹{{ number_format($item->allocated_cost, 2) }}</td>
                                <td class="text-end font-monospace text-success fw-bold">
                                    ₹{{ number_format($item->new_landed_unit_cost, 2) }} / {{ $item->product->uom->code ?? 'PCS' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        @if($voucher->notes)
            <div class="mt-4 pt-3 border-top">
                <x-ui.odoo-form-ui type="textarea" label="Notes / Remarks" name="notes_dummy" value="{{ $voucher->notes }}" readonly="true" />
            </div>
        @endif

    </div>
@endsection
