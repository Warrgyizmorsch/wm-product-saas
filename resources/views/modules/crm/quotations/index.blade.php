@extends('layouts.duralux')

@section('title', 'Quotations | SaaS ERP')
@section('page-title', 'Quotations')
@section('breadcrumb', 'CRM / Quotations')

@section('content')

    <x-ui.card title="All Quotations">
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
                                <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                    @if ($quotation->status === 'Pending Approval')
                                        <form action="{{ route('crm.quotations.approve', $quotation->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <x-ui.button size="sm" variant="soft-success" type="submit" icon="feather-check">
                                                Approve
                                            </x-ui.button>
                                        </form>
                                        <form action="{{ route('crm.quotations.reject', $quotation->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <x-ui.button size="sm" variant="soft-danger" type="submit" icon="feather-x">
                                                Reject
                                            </x-ui.button>
                                        </form>
                                    @endif

                                    <x-ui.icon-btn href="{{ route('crm.quotations.show', $quotation->id) }}" variant="soft-primary" icon="feather-eye" title="View Quotation" />
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
    </x-ui.card>
@endsection
