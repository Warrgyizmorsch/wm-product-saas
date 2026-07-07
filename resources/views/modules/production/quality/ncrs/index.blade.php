@extends('layouts.duralux')

@section('title', 'Non-Conformance Reports Log (NCR) | SaaS ERP')
@section('page-title', 'Non-Conformance Reports (NCR)')
@section('breadcrumb', 'NCR')

@section('page-actions')
    <a href="{{ route('production.ncrs.create') }}" class="btn btn-danger">
        <i class="feather-plus me-2"></i>Log Quality Defect (NCR)
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>NCR Number</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Linked Inspection</th>
                        <th>Order ID</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ncrs as $ncr)
                        <tr>
                            <td class="font-monospace fw-bold text-danger">{{ $ncr->ncr_number }}</td>
                            <td class="text-capitalize">{{ $ncr->category }}</td>
                            <td>
                                @php
                                    $statusClass = match($ncr->status) {
                                        'open' => 'bg-soft-danger text-danger',
                                        'under_review' => 'bg-soft-warning text-warning',
                                        'disposition' => 'bg-soft-primary text-primary',
                                        'closed' => 'bg-soft-success text-success',
                                        default => 'bg-soft-secondary text-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ strtoupper($ncr->status) }}</span>
                            </td>
                            <td>
                                @if($ncr->quality_inspection_id)
                                    <a href="{{ route('production.inspections.show', $ncr->quality_inspection_id) }}">#{{ $ncr->quality_inspection_id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $ncr->production_order_id ?? '—' }}</td>
                            <td>{{ $ncr->created_at->toDateTimeString() }}</td>
                            <td>
                                <a href="{{ route('production.ncrs.show', $ncr->id) }}" class="btn btn-xs btn-outline-danger">Review NCR</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No Non-Conformance Reports (NCR) recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
