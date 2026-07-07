@extends('layouts.duralux')

@section('title', 'Rework Orders Register | SaaS ERP')
@section('page-title', 'Rework Orders')
@section('breadcrumb', 'Rework')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Rework Number</th>
                        <th>Linked NCR</th>
                        <th>Status</th>
                        <th>Estimated Cost</th>
                        <th>Actual Cost</th>
                        <th>Labor Hours</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reworks as $rwk)
                        <tr>
                            <td class="font-monospace fw-bold text-dark">{{ $rwk->rework_number }}</td>
                            <td>{{ $rwk->ncr->ncr_number ?? '—' }}</td>
                            <td>
                                @php
                                    $statusClass = match($rwk->status) {
                                        'draft' => 'bg-soft-secondary text-secondary',
                                        'scheduled' => 'bg-soft-warning text-warning',
                                        'running' => 'bg-soft-primary text-primary',
                                        'completed' => 'bg-soft-success text-success',
                                        default => 'bg-soft-dark text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ strtoupper($rwk->status) }}</span>
                            </td>
                            <td>${{ number_format($rwk->cost_estimate, 2) }}</td>
                            <td class="fw-bold">${{ number_format($rwk->actual_cost, 2) }}</td>
                            <td>{{ number_format($rwk->labor_hours_actual, 2) }} hrs</td>
                            <td>
                                <a href="{{ route('production.rework.show', $rwk->id) }}" class="btn btn-xs btn-outline-dark">Track Rework</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No active Rework Orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
