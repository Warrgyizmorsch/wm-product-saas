@extends('layouts.duralux')

@section('title', 'CAPA Register | SaaS ERP')
@section('page-title', 'Corrective & Preventive Actions (CAPA)')
@section('breadcrumb', 'CAPA')

@section('page-actions')
    <a href="{{ route('production.capas.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Initiate CAPA Investigation
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
                        <th>CAPA Number</th>
                        <th>Linked NCR</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Target Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($capas as $capa)
                        <tr>
                            <td class="font-monospace fw-bold text-primary">{{ $capa->capa_number }}</td>
                            <td>{{ $capa->ncr->ncr_number ?? 'General Quality' }}</td>
                            <td>{{ $capa->owner->name ?? '—' }}</td>
                            <td>
                                @php
                                    $statusClass = match($capa->status) {
                                        'draft' => 'bg-soft-secondary text-secondary',
                                        'active' => 'bg-soft-warning text-warning',
                                        'verified' => 'bg-soft-primary text-primary',
                                        'closed' => 'bg-soft-success text-success',
                                        default => 'bg-soft-dark text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ strtoupper($capa->status) }}</span>
                            </td>
                            <td>{{ $capa->target_date ? $capa->target_date->toDateString() : '—' }}</td>
                            <td>
                                <a href="{{ route('production.capas.show', $capa->id) }}" class="btn btn-xs btn-outline-primary">Open CAPA</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No CAPA records registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
