@extends('layouts.duralux')

@section('title', 'Work Center Queue | SaaS ERP')
@section('page-title', $workCenter->name . ' — Execution Queue')
@section('breadcrumb', 'Work Center Queue')

@section('page-actions')
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>All Work Centers
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded">
                    <i class="feather-settings fs-22"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ $workCenter->name }}</h4>
                    <div class="text-muted fs-13">{{ $workCenter->code ?? '' }}</div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-warning">{{ $queue->where('status', 'running')->count() }}</div>
                        <div class="fs-11 text-muted text-uppercase">Running Jobs</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-info">{{ $queue->where('status', 'ready')->count() + $queue->where('status', 'waiting')->count() }}</div>
                        <div class="fs-11 text-muted text-uppercase">Queued</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-success">{{ $completedToday }}</div>
                        <div class="fs-11 text-muted text-uppercase">Done Today</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-primary">{{ $utilization }}%</div>
                        <div class="fs-11 text-muted text-uppercase">Utilization</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ordered Execution Queue --}}
        <h5 class="fw-bold text-dark mb-3">
            <i class="feather-list me-2"></i>Ordered Execution Queue
        </h5>

        @if($queue->count() > 0)
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 5%" class="text-center">#</th>
                            <th style="width: 15%">Order #</th>
                            <th style="width: 20%">Product</th>
                            <th style="width: 18%">Operation</th>
                            <th style="width: 10%">Machine</th>
                            <th style="width: 12%">Planned Start</th>
                            <th style="width: 12%">Planned Finish</th>
                            <th style="width: 10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($queue as $position => $op)
                            <tr class="{{ $op->status === 'running' ? 'table-warning' : '' }}">
                                <td class="fw-bold text-center align-middle">{{ $position + 1 }}</td>
                                <td class="align-middle">
                                    <a href="{{ route('production.schedules.show', $op->production_schedule_id) }}" class="fw-semibold text-primary fs-12">
                                        {{ $op->order->order_number ?? '—' }}
                                    </a>
                                </td>
                                <td class="align-middle">
                                    <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                </td>
                                <td class="align-middle">
                                    <span class="fw-semibold text-dark fs-12">{{ $op->orderOperation->name ?? '—' }}</span>
                                    <br><small class="text-muted font-monospace">{{ $op->orderOperation->operation_number ?? '' }}</small>
                                </td>
                                <td class="align-middle text-muted fs-12">{{ $op->machine->name ?? '—' }}</td>
                                <td class="align-middle fs-12 text-muted">{{ $op->planned_start->format('d/m H:i') }}</td>
                                <td class="align-middle fs-12 text-muted">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                <td class="align-middle">
                                    @if($op->status === 'running')
                                        <span class="badge bg-soft-warning text-warning">Running</span>
                                    @elseif($op->status === 'ready')
                                        <span class="badge bg-soft-info text-info">Ready</span>
                                    @elseif($op->status === 'waiting')
                                        <span class="erp-badge-draft">Waiting</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-capitalize">{{ $op->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-13">
                <i class="feather-inbox me-2 fs-16"></i>No operations currently queued for this Work Center.
            </div>
        @endif

        {{-- Utilization Bar --}}
        <div class="mt-4 p-3 bg-light rounded">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold text-dark fs-12">Capacity Utilization</span>
                <span class="fw-bold text-primary fs-12">{{ $utilization }}% ({{ number_format($plannedMinutes, 0) }} / {{ number_format($availableMinutes, 0) }} min)</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar {{ $utilization > 90 ? 'bg-danger' : ($utilization > 70 ? 'bg-warning' : 'bg-success') }}"
                     role="progressbar"
                     style="width: {{ $utilization }}%"
                     aria-valuenow="{{ $utilization }}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                </div>
            </div>
            <small class="text-muted fs-11 mt-1 d-flex align-items-center gap-1">
                <i class="feather-clock fs-12"></i>
                <span>
                    @if($shifts->isNotEmpty())
                        Based on active shift{{ $shifts->count() > 1 ? 's' : '' }}:
                        @foreach($shifts as $index => $shift)
                            <strong>{{ $shift->name }}</strong> ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}{{ $shift->break_minutes > 0 ? ', break: ' . $shift->break_minutes . 'm' : '' }}){{ $index < $shifts->count() - 1 ? ', ' : '' }}
                        @endforeach
                        &middot; Adjusted for {{ $workCenter->efficiency_percentage }}% efficiency.
                    @else
                        Based on Standard Shift (8 hours) &middot; Adjusted for {{ $workCenter->efficiency_percentage }}% efficiency. <span class="text-warning">(No active shifts configured; showing fallback)</span> &middot; <a href="{{ route('production.shifts.index') }}" class="text-primary fw-semibold"><i class="feather-settings"></i> Manage Shifts</a>
                    @endif
                </span>
            </small>
        </div>
    </div>
@endsection
