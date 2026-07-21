@extends('layouts.duralux')

@section('title', 'Vendor Payments | SaaS ERP')
@section('page-title', 'Vendor Payments & Allocations')
@section('breadcrumb', 'Purchase / Vendor Payments')

@section('page-actions')
    <x-ui.button href="{{ route('purchase.payments.create') }}" variant="primary" icon="feather-plus">
        Register Vendor Payment
    </x-ui.button>
@endsection

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="shadow-sm mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-credit-card me-2 text-primary"></i>Vendor Payments Ledger
                </h5>
                <small class="text-muted fs-11">View vendor payments and multi-bill allocation records</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form action="{{ route('purchase.payments.index') }}" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search payment no, ref no, vendor..." value="{{ request('search') }}" style="width: 260px;">
                    <button type="submit" class="btn btn-sm btn-light border">Filter</button>
                </form>
            </div>
        </div>

        <div class="table-responsive rounded border">
            <table class="table table-hover align-middle mb-0 text-dark fs-13">
                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                    <tr>
                        <th class="ps-3">Payment Number</th>
                        <th>Vendor</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Reference UTR</th>
                        <th class="text-end pe-3">Paid Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $pay)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('purchase.payments.show', $pay->id) }}" class="fw-bold text-primary">
                                    {{ $pay->payment_number }}
                                </a>
                            </td>
                            <td class="fw-semibold">{{ $pay->vendor?->name ?: '—' }}</td>
                            <td>{{ $pay->payment_date ? $pay->payment_date->format('d-M-Y') : '—' }}</td>
                            <td>
                                <span class="badge bg-soft-primary text-primary fs-11 fw-semibold">{{ $pay->payment_type }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $pay->payment_method }}</span>
                            </td>
                            <td class="font-monospace fs-12">{{ $pay->reference_number ?: 'N/A' }}</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-success">₹{{ number_format($pay->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted fs-13">
                                <i class="feather-info me-1"></i>No vendor payments registered yet. Click "Register Vendor Payment" to post a payment.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $payments->withQueryString()->links() }}
        </div>
    </div>

@endsection
