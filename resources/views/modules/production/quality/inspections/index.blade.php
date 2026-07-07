@extends('layouts.duralux')

@section('title', 'Quality Inspections Log | SaaS ERP')
@section('page-title', 'Quality Inspections Log')
@section('breadcrumb', 'Inspections')

@section('page-actions')
    <a href="{{ route('production.inspections.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>New Inspection Checklist
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
                        <th>ID</th>
                        <th>Quality Plan</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Order ID</th>
                        <th>Audited At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inspections as $insp)
                        <tr>
                            <td class="font-monospace fw-bold">#{{ $insp->id }}</td>
                            <td>{{ $insp->plan->name ?? '—' }}</td>
                            <td class="text-uppercase fs-11 fw-bold">{{ $insp->stage }}</td>
                            <td>
                                @php
                                    $statusClass = match($insp->status) {
                                        'draft' => 'bg-soft-secondary text-secondary',
                                        'submitted' => 'bg-soft-warning text-warning',
                                        'approved' => 'bg-soft-success text-success',
                                        default => 'bg-soft-dark text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ strtoupper($insp->status) }}</span>
                            </td>
                            <td>
                                @php
                                    $resClass = match($insp->result) {
                                        'passed' => 'text-success fw-bold',
                                        'failed' => 'text-danger fw-bold',
                                        default => 'text-warning fw-bold',
                                    };
                                @endphp
                                <span class="{{ $resClass }}">{{ strtoupper($insp->result) }}</span>
                            </td>
                            <td>{{ $insp->production_order_id ?? '—' }}</td>
                            <td>{{ $insp->audited_at ? $insp->audited_at->toDateTimeString() : '—' }}</td>
                            <td>
                                <a href="{{ route('production.inspections.show', $insp->id) }}" class="btn btn-xs btn-outline-dark">Open Check</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No quality inspections found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
