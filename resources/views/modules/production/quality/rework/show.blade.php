@extends('layouts.duralux')

@section('title', 'Rework Execution Track | SaaS ERP')
@section('page-title', 'Rework Execution Shopfloor')
@section('breadcrumb', 'Rework Details')

@section('page-actions')
    <a href="{{ route('production.rework.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Detail Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">Rework Order: {{ $rework->rework_number }}</h4>
                <div class="text-muted fs-12">
                    Linked Non-Conformance Defect: 
                    <strong class="text-dark">
                        @if($rework->ncr)
                            <a href="{{ route('production.ncrs.show', $rework->ncr->id) }}" class="text-primary">
                                {{ $rework->ncr->ncr_number }}
                            </a>
                        @else
                            —
                        @endif
                    </strong>
                </div>
            </div>
            <div>
                <span class="badge bg-soft-primary text-primary px-3 py-1.5 rounded-pill text-uppercase">{{ $rework->status }}</span>
            </div>
        </div>

        {{-- Costs & Time Summary --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Rework Cost Estimate</span>
                    <h5 class="fw-bold text-dark mb-0">${{ number_format($rework->cost_estimate, 2) }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center border-warning bg-soft-warning">
                    <span class="text-warning-emphasis fs-10 text-uppercase d-block mb-1">Actual Rework Cost</span>
                    <h5 class="fw-bold text-warning mb-0">${{ number_format($rework->actual_cost, 2) }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Labor Hours Logged</span>
                    <h5 class="fw-bold text-dark mb-0">{{ number_format($rework->labor_hours_actual, 2) }} hrs</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Machine Hours Logged</span>
                    <h5 class="fw-bold text-dark mb-0">{{ number_format($rework->machine_hours_actual, 2) }} hrs</h5>
                </div>
            </div>
        </div>

        {{-- Operations queue list --}}
        <div class="border-top pt-4">
            <h5 class="fw-bold text-dark mb-3">Rework Operations Execution Queue</h5>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 10%">Sequence</th>
                            <th style="width: 30%">Operation Stage Detail</th>
                            <th style="width: 25%">Work Center Location</th>
                            <th style="width: 15%">Status</th>
                            <th style="width: 20%" class="text-end">Execution Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rework->operations as $op)
                            <tr>
                                <td class="align-middle fw-bold font-monospace text-muted fs-12">{{ $op->sequence }}</td>
                                <td class="align-middle fw-bold text-dark fs-13">{{ $op->name }}</td>
                                <td class="align-middle text-muted fs-13">{{ $op->workCenter->name ?? '—' }}</td>
                                <td class="align-middle">
                                    @php
                                        $opClass = match($op->status) {
                                            'waiting' => 'bg-soft-secondary text-secondary',
                                            'running' => 'bg-soft-primary text-primary',
                                            'completed' => 'bg-soft-success text-success',
                                            default => 'bg-soft-dark text-dark',
                                        };
                                    @endphp
                                    <span class="badge {{ $opClass }} text-uppercase fs-10">{{ $op->status }}</span>
                                </td>
                                <td class="align-middle text-end">
                                    @if($op->status === 'waiting')
                                        <form method="POST" action="{{ route('production.quality.rework.ops.start', $op->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-primary">
                                                <i class="feather-play me-1"></i>Start Op
                                            </button>
                                        </form>
                                    @elseif($op->status === 'running')
                                        <form method="POST" action="{{ route('production.quality.rework.ops.complete', $op->id) }}" class="d-inline-block">
                                            @csrf
                                            <div class="d-flex align-items-center gap-1 justify-content-end">
                                                <input type="number" step="0.1" name="setup_time_actual" class="form-control form-control-sm font-monospace" placeholder="Setup mins" style="width: 95px; height: 26px; padding: 2px 6px;" required>
                                                <button type="submit" class="btn btn-xs btn-success" style="height: 26px;">
                                                    <i class="feather-check me-1"></i>Complete
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <span class="text-success fw-bold fs-12"><i class="feather-check-circle me-1"></i>Completed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
@endsection
