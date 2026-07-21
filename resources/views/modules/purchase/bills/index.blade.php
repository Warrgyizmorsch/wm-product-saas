@extends('layouts.duralux')

@section('title', 'Vendor Bills | SaaS ERP')
@section('page-title', 'Vendor Bills & Invoices')
@section('breadcrumb', 'Purchase / Vendor Bills')

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="shadow-sm mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Error!</strong> {{ session('error') }}
        </x-ui.alert>
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-file-text me-2 text-primary"></i>Vendor Bills (Generated from Approved GRN)
                </h5>
                <small class="text-muted fs-11">Manage vendor invoices generated from Goods Receipt Notes</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form action="{{ route('purchase.bills.index') }}" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search bill no, invoice no, vendor..." value="{{ request('search') }}" style="width: 260px;">
                    <button type="submit" class="btn btn-sm btn-light border">Filter</button>
                </form>
            </div>
        </div>

        <div class="table-responsive rounded border">
            <table class="table table-hover align-middle mb-0 text-dark fs-13">
                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                    <tr>
                        <th class="ps-3">Bill Number</th>
                        <th>Vendor Invoice No</th>
                        <th>Vendor</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Paid Amount</th>
                        <th class="text-end pe-3">Due Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        @php
                            $badgeClass = 'warning';
                            if ($bill->status === 'Paid') $badgeClass = 'success';
                            elseif ($bill->status === 'Partially Paid') $badgeClass = 'info';
                            elseif ($bill->status === 'Posted') $badgeClass = 'primary';
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('purchase.bills.show', $bill->id) }}" class="fw-bold text-primary">
                                    {{ $bill->bill_number }}
                                </a>
                                @if($bill->goodsReceiptNote)
                                    <small class="text-muted d-block fs-11">GRN: {{ $bill->goodsReceiptNote->grn_number }}</small>
                                @endif
                            </td>
                            <td class="font-monospace fw-semibold">{{ $bill->vendor_invoice_number ?: '—' }}</td>
                            <td class="fw-semibold">{{ $bill->vendor?->name ?: '—' }}</td>
                            <td>{{ $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}</td>
                            <td>{{ $bill->due_date ? $bill->due_date->format('d-M-Y') : '—' }}</td>
                            <td>
                                <span class="badge bg-soft-{{ $badgeClass }} text-{{ $badgeClass }} px-2 py-1 fs-11 fw-bold">
                                    {{ $bill->status }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold">₹{{ number_format($bill->grand_total, 2) }}</td>
                            <td class="text-end font-monospace text-success">₹{{ number_format($bill->paid_amount, 2) }}</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-danger">₹{{ number_format($bill->due_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted fs-13">
                                <i class="feather-info me-1"></i>No Vendor Bills generated yet. Vendor Bills are generated from Approved Goods Receipt Notes (GRN).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $bills->withQueryString()->links() }}
        </div>
    </div>

@endsection
