@extends('layouts.duralux')

@section('title', 'Purchase Returns | SaaS ERP')
@section('page-title', 'Purchase Returns')
@section('breadcrumb', 'Purchase / Returns')

@section('page-actions')
    <a href="{{ route('purchase.returns.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Create Return
    </a>
@endsection

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="feather-rotate-ccw me-2 text-primary"></i>Purchase Returns (Debit Notes)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Return Number</th>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th class="text-end">Refund Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($returns as $ret)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('purchase.returns.show', $ret->id) }}">{{ $ret->return_number }}</a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($ret->return_date)) }}</td>
                                <td><span class="fw-bold">{{ $ret->vendor?->name ?: '—' }}</span></td>
                                <td class="text-end fw-bold text-dark">
                                    @php
                                        $retTotal = $ret->total_refund_amount > 0
                                            ? $ret->total_refund_amount
                                            : ($ret->total_amount > 0 ? $ret->total_amount : $ret->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
                                    @endphp
                                    ₹{{ number_format($retTotal, 2) }}
                                </td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($ret->status == 'Completed') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($ret->status == 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $ret->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('purchase.returns.show', $ret->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Return Details">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="feather-rotate-ccw fs-1 mb-2 d-block text-gray-300"></i>
                                    No purchase returns processed yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
