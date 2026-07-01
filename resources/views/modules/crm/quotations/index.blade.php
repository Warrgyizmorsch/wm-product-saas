@extends('layouts.duralux')

@section('title', 'Quotations | SaaS ERP')
@section('page-title', 'Quotations')
@section('breadcrumb', 'CRM / Quotations')

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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="feather-file-text me-2 text-primary"></i>All Quotations
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Quotation #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Expiry Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($quotations as $quotation)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('crm.quotations.show', $quotation->id) }}">{{ $quotation->quotation_number }}</a>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $quotation->customer?->name ?? '—' }}</span>
                                </td>
                                <td>{{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '—' }}</td>
                                <td>{{ $quotation->expiry_date ? $quotation->expiry_date->format('d/m/Y') : '—' }}</td>
                                <td class="fw-bold text-dark">₹{{ number_format($quotation->total_amount, 2) }}</td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($quotation->status === 'Quotation Sent' || $quotation->status === 'Sent') $badgeClass = 'bg-soft-info text-info';
                                        elseif ($quotation->status === 'Accepted' || $quotation->status === 'Approved') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($quotation->status === 'Rejected') $badgeClass = 'bg-soft-danger text-danger';
                                        elseif ($quotation->status === 'Pending Approval') $badgeClass = 'bg-soft-warning text-warning';
                                        elseif ($quotation->status === 'Quotation Rework') $badgeClass = 'bg-soft-warning text-warning';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $quotation->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 align-items-center">
                                        @if ($quotation->status === 'Pending Approval')
                                            <form action="{{ route('crm.quotations.approve', $quotation->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2 fs-11 fw-bold border-0" data-bs-toggle="tooltip" title="Approve Quotation">
                                                    <i class="feather feather-check me-1"></i>Approve
                                                </button>
                                            </form>
                                            <form action="{{ route('crm.quotations.reject', $quotation->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-soft-danger py-1 px-2 fs-11 fw-bold border-0" data-bs-toggle="tooltip" title="Reject Quotation">
                                                    <i class="feather feather-x me-1"></i>Reject
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('crm.quotations.show', $quotation->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Quotation">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-file-text fs-1 mb-2 d-block"></i>
                                    No quotations found in this tenant workspace.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
